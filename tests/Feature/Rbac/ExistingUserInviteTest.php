<?php

namespace Tests\Feature\Rbac;

use App\Mail\OrgInviteMail;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, decision G1(1): an owner can no longer attach an existing account to the organization directly.
 * generateInvite creates an invite for every e-mail and answers the same way; an existing account accepts it while
 * signed in (GET/POST invite/{token}/accept), and only the invited address can.
 */
class ExistingUserInviteTest extends ProjectTestCase
{
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

    private function invite(array $body, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->postJson('org-admin/invite', $body);
    }

    private function holds(User $u, string $slug, ?int $orgId = null): bool
    {
        return DB::table('user_org_roles')->where('user_id', $u->id)->where('org_id', $orgId ?? $this->orgA->id)
            ->where('role_id', $this->roleId($slug))->where('is_active', true)->exists();
    }

    private function storedInvite(string $email, string $roleSlug = 'estimator', array $over = []): string
    {
        $token = $over['token'] ?? 'tok-existing';
        DB::table('org_invites')->insert($over + [
            'token' => $token, 'email' => $email, 'name' => 'Invitee', 'org_id' => $this->orgA->id,
            'role_id' => $this->roleId($roleSlug), 'invited_by' => $this->ownerA->id, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    private function accept(User $u, string $token, string $method = 'post')
    {
        return $this->actingAs($u)->{$method}("invite/$token/accept");
    }

    // ---- the inviter side --------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_inviting_an_existing_account_creates_an_invite_and_assigns_nothing(string $mode): void
    {
        $this->setMode($mode);
        $rolesBefore = DB::table('user_org_roles')->count();

        $r = $this->invite(['email' => $this->invitee->email, 'name' => 'Whoever', 'role_id' => $this->roleId('estimator')])
            ->assertOk()->assertJson(['type' => 'invite', 'email_sent' => true]);

        $this->assertSame($rolesBefore, DB::table('user_org_roles')->count());
        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertSame(0, DB::table('role_assignment_logs')->where('target_user_id', $this->invitee->id)->count());
        $row = DB::table('org_invites')->first();
        $this->assertSame($this->invitee->email, $row->email);
        $this->assertSame($this->ownerA->id, (int) $row->invited_by);
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame($this->roleId('estimator'), (int) $row->role_id);
        $this->assertNull($row->used_at);
        $this->assertSame(url("/invite/{$row->token}/accept"), $r->json('link'));
    }

    #[DataProvider('modes')]
    public function test_the_response_discloses_nothing_about_the_account_and_has_the_shape_of_a_new_invite(string $mode): void
    {
        $this->setMode($mode);

        $existing = $this->invite(['email' => $this->invitee->email, 'name' => 'Whoever', 'role_id' => $this->roleId('estimator')]);
        $fresh = $this->invite(['email' => 'brand-new@example.test', 'name' => 'Whoever', 'role_id' => $this->roleId('estimator')]);

        $this->assertSame(array_keys($fresh->json()), array_keys($existing->json()));
        $this->assertSame('invite', $existing->json('type'));
        $this->assertStringNotContainsString($this->invitee->name, $existing->getContent());
        $this->assertNull($existing->json('user_name'));
        $this->assertNotSame('confirm_existing', $existing->json('type'));
        $this->assertNotSame('existing', $existing->json('type'));
    }

    #[DataProvider('modes')]
    public function test_a_confirmed_flag_no_longer_assigns_directly(string $mode): void
    {
        $this->setMode($mode);

        $this->invite(['email' => $this->invitee->email, 'name' => 'W', 'role_id' => $this->roleId('estimator'), 'confirmed' => true])->assertOk();

        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertSame(1, DB::table('org_invites')->count());
    }

    public function test_the_existing_account_mail_points_to_the_accept_page_and_new_mail_is_unchanged(): void
    {
        $this->invite(['email' => $this->invitee->email, 'name' => 'Invitee', 'role_id' => $this->roleId('estimator')])->assertOk();
        $this->invite(['email' => 'brand-new@example.test', 'name' => 'Newbie', 'role_id' => $this->roleId('estimator')])->assertOk();

        $existingToken = DB::table('org_invites')->where('email', $this->invitee->email)->value('token');
        $freshToken = DB::table('org_invites')->where('email', 'brand-new@example.test')->value('token');

        Mail::assertSent(OrgInviteMail::class, fn ($m) => $m->hasTo($this->invitee->email)
            && $m->existingAccount === true && $m->inviteUrl === url("/invite/$existingToken/accept"));
        Mail::assertSent(OrgInviteMail::class, fn ($m) => $m->hasTo('brand-new@example.test')
            && $m->existingAccount === false && $m->inviteUrl === url("/register/invite/$freshToken"));
        Mail::assertSent(OrgInviteMail::class, 2);
    }

    public function test_the_mail_text_differs_for_an_existing_account(): void
    {
        $this->invite(['email' => $this->invitee->email, 'name' => 'Invitee', 'role_id' => $this->roleId('estimator')])->assertOk();
        $this->invite(['email' => 'brand-new@example.test', 'name' => 'Newbie', 'role_id' => $this->roleId('estimator')])->assertOk();

        Mail::assertSent(OrgInviteMail::class, function ($m) {
            $html = $m->render();
            if ($m->existingAccount) {
                return str_contains($html, 'Sign in to accept') && str_contains($html, 'already have a Wisselbanken account');
            }

            return str_contains($html, 'Accept invitation') && ! str_contains($html, 'already have a Wisselbanken account');
        });
    }

    public function test_send_email_false_returns_the_link_without_mailing_and_a_prior_invite_is_replaced(): void
    {
        $this->invite(['email' => $this->invitee->email, 'name' => 'Invitee', 'role_id' => $this->roleId('estimator'), 'send_email' => false])
            ->assertOk()->assertJson(['email_sent' => false]);
        $this->invite(['email' => $this->invitee->email, 'name' => 'Invitee', 'role_id' => $this->roleId('project_manager'), 'send_email' => false])->assertOk();

        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('org_invites')->count());
        $this->assertSame($this->roleId('project_manager'), (int) DB::table('org_invites')->value('role_id'));
    }

    // ---- the invitee side --------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_invited_account_reviews_and_accepts_and_gets_the_role(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);

        $this->accept($this->invitee, $token, 'get')->assertOk()->assertSee('Org A')->assertSee('Estimator')->assertSee('Accept invitation');
        $this->assertFalse($this->holds($this->invitee, 'estimator'));

        $this->accept($this->invitee, $token)->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('success', 'Welcome! You have joined Org A as Estimator.')
            ->assertSessionHas(config('rbac.current_org_session_key'), $this->orgA->id);

        $this->assertTrue($this->holds($this->invitee, 'estimator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $log = DB::table('role_assignment_logs')->where('target_user_id', $this->invitee->id)->where('action', 'assigned')->first();
        $this->assertSame($this->ownerA->id, (int) $log->performed_by);
        $this->assertSame($this->orgA->id, (int) $log->org_id);
        $this->assertTrue($this->holds($this->invitee, 'viewer_read_only', DB::table('user_org_roles')->where('user_id', $this->invitee->id)->where('org_id', '!=', $this->orgA->id)->value('org_id')), 'the old membership is untouched');
    }

    #[DataProvider('modes')]
    public function test_an_invite_is_one_time(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);
        $this->accept($this->invitee, $token)->assertRedirect(route('user.dashboard'));
        DB::table('user_org_roles')->where('user_id', $this->invitee->id)->where('org_id', $this->orgA->id)->update(['is_active' => false]);

        $this->accept($this->invitee, $token)->assertNotFound();
        $this->accept($this->invitee, $token, 'get')->assertNotFound();

        $this->assertFalse($this->holds($this->invitee, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_another_signed_in_account_gets_a_404_and_changes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->storedInvite($this->invitee->email);
        $rolesBefore = DB::table('user_org_roles')->count();

        $this->accept($this->stranger2, $token, 'get')->assertNotFound();
        $this->accept($this->stranger2, $token)->assertNotFound();
        $this->accept($this->ownerA, $token)->assertNotFound();

        $this->assertSame($rolesBefore, DB::table('user_org_roles')->count());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->accept($this->invitee, 'no-such-token')->assertNotFound();
    }

    public function test_the_e_mail_match_ignores_case_and_surrounding_spaces(): void
    {
        $token = $this->storedInvite('  '.strtoupper($this->invitee->email).' ');

        $this->accept($this->invitee, $token)->assertRedirect(route('user.dashboard'));

        $this->assertTrue($this->holds($this->invitee, 'estimator'));
    }

    public function test_an_unverified_account_with_the_invited_address_cannot_accept(): void
    {
        $unverified = User::factory()->unverified()->create(['role' => 'user']);
        $token = $this->storedInvite($unverified->email);

        $this->accept($unverified, $token)->assertRedirect(route('verification.notice'));
        $this->accept($unverified, $token, 'get')->assertRedirect(route('verification.notice'));

        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $unverified->id)->count());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_the_controller_itself_refuses_an_unverified_account_even_without_the_route_middleware(): void
    {
        $unverified = User::factory()->unverified()->create(['role' => 'user']);
        $token = $this->storedInvite($unverified->email);
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\EnsureEmailIsVerified::class);

        $this->accept($unverified, $token)->assertNotFound();
        $this->accept($unverified, $token, 'get')->assertNotFound();

        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $unverified->id)->count());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_an_invite_used_by_a_concurrent_request_between_load_and_claim_assigns_nothing(): void
    {
        $token = $this->storedInvite($this->invitee->email);
        $raced = false;
        DB::listen(function ($query) use (&$raced) {
            if (! $raced && str_contains($query->sql, 'from "org_invites"')) {
                $raced = true;
                DB::table('org_invites')->update(['used_at' => now()]);
            }
        });

        $this->accept($this->invitee, $token)->assertNotFound();

        $this->assertTrue($raced);
        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertSame(0, DB::table('role_assignment_logs')->where('target_user_id', $this->invitee->id)->count());
    }

    public function test_an_expired_invite_is_refused_for_the_right_account(): void
    {
        $token = $this->storedInvite($this->invitee->email, 'estimator', ['expires_at' => now()->subMinute()]);

        $this->accept($this->invitee, $token, 'get')->assertNotFound()->assertSee('Invite link invalid or expired');
        $this->accept($this->invitee, $token)->assertNotFound();

        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_the_role_is_re_validated_at_accept(): void
    {
        $platform = $this->storedInvite($this->invitee->email, 'platform_support', ['token' => 'tok-p']);
        $seller = $this->storedInvite($this->invitee->email, 'sales_rep', ['token' => 'tok-s']);

        foreach ([$platform, $seller] as $token) {
            $this->accept($this->invitee, $token, 'get')->assertNotFound();
            $this->accept($this->invitee, $token)->assertNotFound();
            $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        }
        $this->assertFalse($this->holds($this->invitee, 'platform_support'));
        $this->assertFalse($this->holds($this->invitee, 'sales_rep'));

        $mfr = Organization::create(['name' => 'Mfr', 'org_type' => 'manufacturer', 'team_size' => 'solo']);
        $mfrOwner = $this->mkUser($mfr, 'organization_owner');
        $token = $this->storedInvite($this->invitee->email, 'sales_rep', ['token' => 'tok-m', 'org_id' => $mfr->id, 'invited_by' => $mfrOwner->id]);
        $this->accept($this->invitee, $token)->assertRedirect(route('user.dashboard'));
        $this->assertTrue($this->holds($this->invitee, 'sales_rep', $mfr->id));
    }

    #[DataProvider('modes')]
    public function test_separation_of_duties_still_applies_and_leaves_the_invite_unused(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->invitee, $this->orgA, 'executive_approver');
        $token = $this->storedInvite($this->invitee->email, 'estimator');

        $this->accept($this->invitee, $token)->assertRedirect(route('invite.review', $token))->assertSessionHas('error');

        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->accept($this->invitee, $token, 'get')->assertOk();
    }

    public function test_a_platform_admin_account_cannot_use_the_accept_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $this->storedInvite($admin->email);

        $this->accept($admin, $token)->assertRedirect('login');

        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $admin->id)->count());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_a_guest_is_sent_to_login_and_returns_to_the_invite_afterwards(): void
    {
        $token = $this->storedInvite($this->invitee->email);
        $accept = url("/invite/$token/accept");

        $this->get("invite/$token/accept")->assertRedirect(route('login'))->assertSessionHas('url.intended', $accept);
        $this->post("invite/$token/accept")->assertRedirect(route('login'));

        $this->withSession(['url.intended' => $accept])
            ->post('login', ['email' => $this->invitee->email, 'password' => 'password'])
            ->assertRedirect($accept);

        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->invitee->id)->where('org_id', $this->orgA->id)->count());
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_the_register_link_flow_for_a_new_account_is_untouched(): void
    {
        $this->invite(['email' => 'brand-new@example.test', 'name' => 'Newbie', 'role_id' => $this->roleId('estimator')])->assertOk();
        $token = DB::table('org_invites')->value('token');
        $this->app['auth']->forgetGuards();

        $this->get("register/invite/$token")->assertOk();
        $this->post("register/invite/$token", ['name' => 'Newbie', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('user.dashboard'));

        $user = User::where('email', 'brand-new@example.test')->first();
        $this->assertTrue($this->holds($user, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_the_answers_for_an_existing_and_a_new_address_differ_only_in_the_link(string $mode): void
    {
        $this->setMode($mode);

        $existing = $this->invite(['email' => $this->invitee->email, 'name' => 'Whoever', 'role_id' => $this->roleId('estimator')])->assertOk();
        $fresh = $this->invite(['email' => 'brand-new@example.test', 'name' => 'Whoever', 'role_id' => $this->roleId('estimator')])->assertOk();

        $this->assertSame(Arr::except($fresh->json(), ['link']), Arr::except($existing->json(), ['link']));
    }

    #[DataProvider('modes')]
    public function test_the_returned_link_is_the_register_link_for_a_new_address_and_the_accept_link_for_an_existing_account(string $mode): void
    {
        $this->setMode($mode);

        $fresh = $this->invite(['email' => 'brand-new@example.test', 'name' => 'Newbie', 'role_id' => $this->roleId('estimator')])->assertOk();
        $existing = $this->invite(['email' => $this->invitee->email, 'name' => 'Invitee', 'role_id' => $this->roleId('estimator')])->assertOk();

        $freshToken = DB::table('org_invites')->where('email', 'brand-new@example.test')->value('token');
        $existingToken = DB::table('org_invites')->where('email', $this->invitee->email)->value('token');
        $this->assertSame(url("/register/invite/$freshToken"), $fresh->json('link'));
        $this->assertSame(url("/invite/$existingToken/accept"), $existing->json('link'));
    }

    #[DataProvider('modes')]
    public function test_separation_of_duties_also_applies_when_the_invited_role_is_the_approver_role(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->invitee, $this->orgA, 'estimator');
        $token = $this->storedInvite($this->invitee->email, 'executive_approver');

        $this->accept($this->invitee, $token)->assertRedirect(route('invite.review', $token))->assertSessionHas('error');

        $this->assertFalse($this->holds($this->invitee, 'executive_approver'));
        $this->assertTrue($this->holds($this->invitee, 'estimator'));
        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_the_review_page_posts_to_the_accept_route_with_a_csrf_token(): void
    {
        $token = $this->storedInvite($this->invitee->email);

        $html = $this->accept($this->invitee, $token, 'get')->assertOk()->getContent();

        $this->assertStringContainsString('method="POST" action="'.route('invite.accept', $token).'"', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="_token" value="[A-Za-z0-9]{40}"/', $html);
    }

    #[DataProvider('modes')]
    public function test_separation_of_duties_is_checked_per_organization_at_accept(string $mode): void
    {
        $this->setMode($mode);
        $orgC = DB::table('user_org_roles')->where('user_id', $this->invitee->id)->value('org_id');
        $this->assignRole($this->invitee, Organization::findOrFail($orgC), 'estimator');
        $token = $this->storedInvite($this->invitee->email, 'executive_approver');

        $this->accept($this->invitee, $token)->assertRedirect(route('user.dashboard'));

        $this->assertTrue($this->holds($this->invitee, 'executive_approver'));
        $this->assertTrue($this->holds($this->invitee, 'estimator', (int) $orgC));
        $this->assertFalse($this->holds($this->invitee, 'estimator'));
    }
}
