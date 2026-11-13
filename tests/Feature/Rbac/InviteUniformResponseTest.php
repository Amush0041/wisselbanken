<?php

namespace Tests\Feature\Rbac;

use App\Mail\OrgInviteMail;
use App\Models\Rbac\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2 round 2, O11: inviting an address looks the same whether or not it already has an account. One JSON answer
 * and link form (GET invite/{token}, route invite.entry), one e-mail text, one guest page, no users lookup in generateInvite,
 * and POST register/invite/{token} for an address that already has an account creates nothing and sends the person to login.
 *
 * What sqlite cannot prove: the register guard uses User::where('email', ...)->exists(); MariaDB compares e-mails case
 * insensitively and sqlite does not, so the case-variant of an existing address is not exercised here.
 */
class InviteUniformResponseTest extends ProjectTestCase
{
    private const NEW_ADDRESS = 'brand-new@example.test';
    private const NAME = 'Whoever';

    private User $ownerA;
    private User $invitee;
    private User $stranger2;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();
        Mail::fake();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $orgC = $this->mkOrg('Org C');
        $this->invitee = $this->mkUser($orgC, 'viewer_read_only');
        $this->stranger2 = $this->mkUser($orgC, 'viewer_read_only');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function invite(array $body, ?User $actor = null, ?int $orgId = null)
    {
        return $this->actingAs($actor ?? $this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->postJson('org-admin/invite', $body);
    }

    private function body(string $email, array $over = []): array
    {
        return $over + ['email' => $email, 'name' => self::NAME, 'role_id' => $this->roleId('estimator')];
    }

    private function tokenOf(string $email): string
    {
        return (string) DB::table('org_invites')->where('email', $email)->value('token');
    }

    private function storedInvite(string $email, string $token = 'tok-stored', array $over = []): string
    {
        DB::table('org_invites')->insert($over + [
            'token' => $token, 'email' => $email, 'name' => self::NAME, 'org_id' => $this->orgA->id,
            'role_id' => $this->roleId('estimator'), 'invited_by' => $this->ownerA->id, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    private function guest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function normalised(string $html, string $token, string $email): string
    {
        $html = str_replace([$token, $email, e($email)], ['TOKEN', 'EMAIL', 'EMAIL'], $html);

        return (string) preg_replace('/(name="_token" value=")[A-Za-z0-9]+(")/', '$1CSRF$2', $html);
    }

    private function holds(User $u, string $slug): bool
    {
        return DB::table('user_org_roles')->where('user_id', $u->id)->where('org_id', $this->orgA->id)
            ->where('role_id', $this->roleId($slug))->where('is_active', true)->exists();
    }

    private function counts(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'roles' => DB::table('user_org_roles')->count(),
            'logs' => DB::table('role_assignment_logs')->count(),
        ];
    }

    // ---- POST org-admin/invite: the answer ----------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_answer_has_the_same_keys_types_and_link_form_for_an_account_and_a_new_address(string $mode): void
    {
        $this->setMode($mode);

        $existing = $this->invite($this->body($this->invitee->email))->assertOk();
        $fresh = $this->invite($this->body(self::NEW_ADDRESS))->assertOk();

        $this->assertSame(array_keys($fresh->json()), array_keys($existing->json()));
        $this->assertSame(array_map('gettype', $fresh->json()), array_map('gettype', $existing->json()));
        $this->assertSame(['type' => 'invite', 'email_sent' => true], Arr::except($existing->json(), ['link']));
        $this->assertSame(Arr::except($fresh->json(), ['link']), Arr::except($existing->json(), ['link']));
        $this->assertSame(url('/invite/'.$this->tokenOf($this->invitee->email)), $existing->json('link'));
        $this->assertSame(url('/invite/'.$this->tokenOf(self::NEW_ADDRESS)), $fresh->json('link'));
        $this->assertSame(48, strlen($this->tokenOf($this->invitee->email)));
        $this->assertStringNotContainsString($this->invitee->name, $existing->getContent());
        $this->assertStringNotContainsString('/accept', $existing->getContent());
        $this->assertStringNotContainsString('register/invite', $fresh->getContent());
    }

    #[DataProvider('modes')]
    public function test_without_an_e_mail_the_answer_is_just_as_uniform(string $mode): void
    {
        $this->setMode($mode);

        $existing = $this->invite($this->body($this->invitee->email, ['send_email' => false]))->assertOk();
        $fresh = $this->invite($this->body(self::NEW_ADDRESS, ['send_email' => false]))->assertOk();

        $this->assertSame(['type' => 'invite', 'email_sent' => false], Arr::except($existing->json(), ['link']));
        $this->assertSame(Arr::except($fresh->json(), ['link']), Arr::except($existing->json(), ['link']));
        $this->assertSame(url('/invite/'.$this->tokenOf($this->invitee->email)), $existing->json('link'));
        Mail::assertNothingSent();
    }

    #[DataProvider('modes')]
    public function test_the_stored_invites_differ_only_in_token_and_address(string $mode): void
    {
        $this->setMode($mode);

        $this->invite($this->body($this->invitee->email))->assertOk();
        $this->invite($this->body(self::NEW_ADDRESS))->assertOk();

        $rows = DB::table('org_invites')->orderBy('id')->get()->map(fn ($r) => Arr::except((array) $r, ['id', 'token', 'email', 'created_at', 'updated_at', 'expires_at']))->all();
        $this->assertSame($rows[0], $rows[1]);
        $this->assertSame($this->invitee->email, DB::table('org_invites')->orderBy('id')->value('email'));
        $this->assertSame($this->orgA->id, (int) DB::table('org_invites')->value('org_id'));
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->invitee->id)->where('org_id', $this->orgA->id)->count(), 'inviting assigns nothing');
    }

    #[DataProvider('modes')]
    public function test_the_two_mails_render_the_same_after_the_token_is_normalised(string $mode): void
    {
        $this->setMode($mode);
        $this->invite($this->body($this->invitee->email))->assertOk();
        $this->invite($this->body(self::NEW_ADDRESS))->assertOk();
        Mail::assertSent(OrgInviteMail::class, 2);

        $rendered = [];
        foreach ([$this->invitee->email, self::NEW_ADDRESS] as $email) {
            $token = $this->tokenOf($email);
            $mail = Mail::sent(OrgInviteMail::class, fn ($m) => $m->hasTo($email))->first();

            $this->assertSame(url("/invite/$token"), $mail->inviteUrl);
            $html = $mail->render();
            $this->assertStringContainsString(url("/invite/$token"), $html);
            $this->assertStringNotContainsString('/accept', $html);
            $this->assertStringNotContainsString('register/invite', $html);
            $rendered[] = [str_replace($token, 'TOKEN', $html), $mail->envelope()->subject, $mail->recipientName, $mail->roleName, $mail->orgName, $mail->inviterName];
        }

        $this->assertSame($rendered[0], $rendered[1]);
        $this->assertStringContainsString('New to Wisselbanken?', $rendered[0][0]);
        $this->assertStringContainsString('Already have an account? Sign in with this e-mail address', $rendered[0][0]);
        $this->assertStringContainsString('Accept invitation', $rendered[0][0]);
    }

    #[DataProvider('modes')]
    public function test_generate_invite_never_looks_up_the_address_in_users_and_runs_the_same_queries(string $mode): void
    {
        $this->setMode($mode);
        $this->invite($this->body('warm-up@example.test'))->assertOk();

        $runs = [];
        foreach ([$this->invitee->email, self::NEW_ADDRESS] as $email) {
            $log = new \ArrayObject();
            DB::listen(function ($q) use ($log) {
                $log[] = [$q->sql, $q->bindings];
            });
            $this->invite($this->body($email))->assertOk();
            $runs[$email] = $log->getArrayCopy();
        }

        foreach ($runs as $email => $log) {
            $this->assertNotEmpty($log);
            foreach ($log as [$sql, $bindings]) {
                $this->assertFalse(
                    str_contains($sql, '"users"') && in_array($email, $bindings, true),
                    "a users query with the invited address: $sql"
                );
                $this->assertDoesNotMatchRegularExpression('/from\s+"users"\s+where\s+("users"\.)?"email"/i', $sql);
            }
        }
        $statements = fn (array $log) => array_column($log, 0);
        $this->assertSame($statements($runs[self::NEW_ADDRESS]), $statements($runs[$this->invitee->email]), 'the same statements in the same order');
    }

    // ---- POST org-admin/invite: the denied paths -------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_member_below_the_level_cannot_invite_and_nothing_is_written(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->viewer, $this->super] as $actor) {
            $this->invite($this->body($this->invitee->email), $actor)->assertForbidden();
            $this->invite($this->body(self::NEW_ADDRESS), $actor)->assertForbidden();
        }

        $this->assertSame(0, DB::table('org_invites')->count());
        Mail::assertNothingSent();
    }

    #[DataProvider('modes')]
    public function test_a_user_without_an_organization_cannot_invite(string $mode): void
    {
        $this->setMode($mode);
        $nobody = $this->mkUser();

        $this->invite($this->body($this->invitee->email), $nobody)->assertForbidden();

        $this->assertSame(0, DB::table('org_invites')->count());
        Mail::assertNothingSent();
    }

    #[DataProvider('modes')]
    public function test_an_owner_can_only_invite_into_the_organization_he_belongs_to(string $mode): void
    {
        $this->setMode($mode);
        $ownerB = $this->mkUser($this->orgB, 'organization_owner');

        $this->invite($this->body(self::NEW_ADDRESS), $ownerB, $this->orgA->id)->assertOk();

        $this->assertSame($this->orgB->id, (int) DB::table('org_invites')->value('org_id'), 'a forged session org does not widen the inviter');
        $this->assertSame($ownerB->id, (int) DB::table('org_invites')->value('invited_by'));
    }

    #[DataProvider('modes')]
    public function test_an_unknown_role_and_roles_the_org_cannot_assign_are_refused_the_same_for_every_address(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->invitee->email, self::NEW_ADDRESS] as $email) {
            $this->invite($this->body($email, ['role_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('role_id');
            $this->invite($this->body($email, ['role_id' => $this->roleId('platform_support')]))->assertForbidden();
            $this->invite($this->body($email, ['role_id' => $this->roleId('sales_rep')]))->assertForbidden();
        }

        $this->assertSame(0, DB::table('org_invites')->count());
        Mail::assertNothingSent();
    }

    // ---- GET invite/{token}: the guest page ----------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_guest_sees_the_same_page_for_an_account_and_a_new_address(string $mode): void
    {
        $this->setMode($mode);
        $this->invite($this->body($this->invitee->email))->assertOk();
        $this->invite($this->body(self::NEW_ADDRESS))->assertOk();
        $this->guest();

        $pages = [];
        foreach ([$this->invitee->email, self::NEW_ADDRESS] as $email) {
            $token = $this->tokenOf($email);
            $entry = $this->get("invite/$token")->assertOk();
            $register = $this->get("register/invite/$token")->assertOk();

            $entryHtml = $this->normalised($entry->getContent(), $token, $email);
            $this->assertSame($this->normalised($register->getContent(), $token, $email), $entryHtml, 'the entry renders what register/invite/{token} renders');
            $this->assertStringContainsString('Sign in to accept this invitation', $entryHtml);
            $this->assertStringContainsString(route('invite.review', 'TOKEN'), str_replace($token, 'TOKEN', $entry->getContent()));
            $pages[] = $entryHtml;
        }

        $this->assertSame($pages[0], $pages[1]);
        $this->assertGuest();
    }

    #[DataProvider('modes')]
    public function test_a_guest_gets_the_same_invalid_page_for_an_unknown_used_or_expired_token_on_both_routes(string $mode): void
    {
        $this->setMode($mode);
        $used = $this->storedInvite($this->invitee->email, 'tok-used', ['used_at' => now()]);
        $expired = $this->storedInvite(self::NEW_ADDRESS, 'tok-expired', ['expires_at' => now()->subMinute()]);

        $bodies = [];
        foreach (['no-such-token', $used, $expired] as $token) {
            $entry = $this->get("invite/$token")->assertOk();
            $register = $this->get("register/invite/$token")->assertOk();
            $this->assertSame($register->getContent(), $entry->getContent(), $token);
            $this->assertStringContainsString('Invite link invalid or expired', $entry->getContent());
            $bodies[] = $entry->getContent();
        }

        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($bodies[0], $bodies[2]);
    }

    // ---- GET invite/{token}: signed in -----------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_signed_in_user_is_sent_to_the_accept_page_and_the_right_one_reaches_it(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);

        $this->actingAs($this->invitee)->get("invite/$token")->assertRedirect(route('invite.review', $token));
        $this->actingAs($this->invitee)->get(route('invite.review', $token))->assertOk()->assertSee('Org A')->assertSee('Accept invitation');
        $this->assertFalse($this->holds($this->invitee, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_a_signed_in_wrong_user_and_an_unknown_token_end_at_the_same_404(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);

        $this->actingAs($this->stranger2)->get("invite/$token")->assertRedirect(route('invite.review', $token));
        $wrongUser = $this->actingAs($this->stranger2)->get(route('invite.review', $token))->assertNotFound();
        $this->actingAs($this->stranger2)->get('invite/no-such-token')->assertRedirect(route('invite.review', 'no-such-token'));
        $unknown = $this->actingAs($this->stranger2)->get(route('invite.review', 'no-such-token'))->assertNotFound();
        $ownerView = $this->actingAs($this->ownerA)->get(route('invite.review', $token))->assertNotFound();

        $this->assertSame($unknown->getContent(), $wrongUser->getContent());
        $this->assertSame($unknown->getContent(), $ownerView->getContent());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_a_signed_in_user_with_an_unverified_address_is_sent_to_verification_not_shown_the_invite(): void
    {
        $unverified = User::factory()->unverified()->create(['role' => 'user']);
        $token = $this->storedInvite($unverified->email);

        $this->actingAs($unverified)->get("invite/$token")->assertRedirect(route('invite.review', $token));
        $this->actingAs($unverified)->get(route('invite.review', $token))->assertRedirect(route('verification.notice'));

        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $unverified->id)->count());
    }

    public function test_the_right_user_with_an_expired_or_used_invite_gets_the_invalid_page_with_a_404(): void
    {
        $expired = $this->storedInvite($this->invitee->email, 'tok-expired', ['expires_at' => now()->subMinute()]);
        $used = $this->storedInvite($this->invitee->email, 'tok-used', ['used_at' => now()]);

        foreach ([$expired, $used] as $token) {
            $this->actingAs($this->invitee)->get("invite/$token")->assertRedirect(route('invite.review', $token));
            $this->actingAs($this->invitee)->get(route('invite.review', $token))->assertNotFound()->assertSee('Invite link invalid or expired');
        }
    }

    #[DataProvider('modes')]
    public function test_a_signed_in_user_is_still_turned_away_from_the_register_routes_and_nothing_is_created(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite(self::NEW_ADDRESS);
        $before = $this->counts();

        foreach ([$this->invitee, $this->ownerA] as $user) {
            $this->actingAs($user)->get("register/invite/$token")->assertRedirect();
            $this->actingAs($user)->post("register/invite/$token", ['name' => 'X', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect();
        }

        $this->assertSame($before, $this->counts());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    // ---- POST register/invite/{token} ------------------------------------------------------------------------------------

    private function registerBody(array $over = []): array
    {
        return $over + ['name' => 'Chosen Name', 'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    #[DataProvider('modes')]
    public function test_registering_through_an_invite_for_an_address_with_an_account_creates_nothing_and_goes_to_login(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);
        $before = $this->counts();
        $this->guest();

        foreach ([1, 2] as $attempt) {
            $this->post("register/invite/$token", $this->registerBody())
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', 'Sign in to accept this invitation.')
                ->assertSessionHas('url.intended', route('invite.review', $token))
                ->assertSessionMissing('error');

            $this->assertSame($before, $this->counts(), "attempt $attempt");
            $this->assertGuest();
        }
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->assertSame($this->invitee->name, User::find($this->invitee->id)->name, 'the existing account is untouched');
    }

    #[DataProvider('modes')]
    public function test_the_person_then_signs_in_and_accepts_the_invitation(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);
        $this->guest();
        $this->post("register/invite/$token", $this->registerBody())->assertRedirect(route('login'));

        $this->post('login', ['email' => $this->invitee->email, 'password' => 'password'])->assertRedirect(route('invite.review', $token));
        $this->post("invite/$token/accept")->assertRedirect(route('user.dashboard'));

        $this->assertTrue($this->holds($this->invitee, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_a_new_address_still_registers_through_the_same_route(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite(self::NEW_ADDRESS);
        $this->guest();

        $this->post("register/invite/$token", $this->registerBody())->assertRedirect(route('user.dashboard'));

        $user = User::where('email', self::NEW_ADDRESS)->firstOrFail();
        $this->assertSame('Chosen Name', $user->name);
        $this->assertTrue($this->holds($user, 'estimator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('modes')]
    public function test_a_bad_form_gets_the_same_validation_errors_for_an_account_and_a_new_address(string $mode): void
    {
        $this->setMode($mode);
        $existing = $this->storedInvite($this->invitee->email, 'tok-existing');
        $fresh = $this->storedInvite(self::NEW_ADDRESS, 'tok-fresh');
        $before = $this->counts();
        $this->guest();

        $forms = [
            'missing name' => $this->registerBody(['name' => '']),
            'short password' => $this->registerBody(['password' => 'short', 'password_confirmation' => 'short']),
            'mismatch' => $this->registerBody(['password_confirmation' => 'different123']),
        ];
        foreach ($forms as $label => $form) {
            $a = $this->postJson("register/invite/$existing", $form)->assertStatus(422);
            $b = $this->postJson("register/invite/$fresh", $form)->assertStatus(422);

            $this->assertSame($b->json('errors'), $a->json('errors'), $label);
            $this->assertSame($b->json('message'), $a->json('message'), $label);
        }

        $this->assertSame($before, $this->counts());
        $this->assertNull(DB::table('org_invites')->whereIn('token', [$existing, $fresh])->whereNotNull('used_at')->value('id'));
    }

    // ---- links in flight keep working ----------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_an_invite_sent_before_the_change_opens_at_the_old_urls(string $mode): void
    {
        $this->setMode($mode);
        $roleName = (string) Role::where('slug', 'estimator')->value('name');
        $newToken = $this->storedInvite(self::NEW_ADDRESS, 'tok-old-new');
        $accountToken = $this->storedInvite($this->invitee->email, 'tok-old-account');
        $this->guest();

        $this->get("register/invite/$newToken")->assertOk()->assertSee($roleName);
        $this->get("register/invite/$accountToken")->assertOk()->assertSee($roleName);

        $this->actingAs($this->invitee)->get("invite/$accountToken/accept")->assertOk()->assertSee($roleName)->assertSee('Accept invitation');
        $this->actingAs($this->invitee)->post("invite/$accountToken/accept")->assertRedirect(route('user.dashboard'));
        $this->assertTrue($this->holds($this->invitee, 'estimator'));

        $this->guest();
        $this->post("register/invite/$newToken", $this->registerBody())->assertRedirect(route('user.dashboard'));
        $this->assertTrue($this->holds(User::where('email', self::NEW_ADDRESS)->firstOrFail(), 'estimator'));
    }

    // ---- the route itself --------------------------------------------------------------------------------------------------------

    public function test_the_entry_route_is_public_and_not_in_the_permission_map(): void
    {
        $route = Route::getRoutes()->getByName('invite.entry');

        $this->assertNotNull($route);
        $this->assertSame('invite/{token}', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertNotContains('guest', $route->gatherMiddleware());
        $this->assertNotContains('verified', $route->gatherMiddleware());
        $this->assertArrayNotHasKey('GET invite/{token}', config('route_permission_map'));

        $this->assertNotContains('guest', $route->controllerMiddleware(), 'the controller-level guest middleware excludes the entry');
        foreach (['invite.show', 'invite.register'] as $name) {
            $guarded = Route::getRoutes()->getByName($name);
            $this->assertContains('guest', $guarded->middleware(), "$name: the route itself carries guest");
            $this->assertContains('guest', $guarded->controllerMiddleware(), "$name: so does the controller");
        }
        foreach (['invite.review', 'invite.accept'] as $name) {
            $this->assertContains('auth', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
            $this->assertContains('verified', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }
}
