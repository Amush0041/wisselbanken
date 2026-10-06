<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\ApiToken;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\Organization;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Limits lifted for listed universal admins only (org delete, ownership transfer, revoking another user's
 * delegation / API token, staff_notes). Production hard requirement: nothing changes for anybody else.
 */
class UniversalAdminLiftedLimitsTest extends ProjectTestCase
{
    private User $ua;
    private User $other;
    private Organization $orgC;
    private Organization $orgD;
    private User $ownerC;
    private User $adminC;
    private User $viewerC;
    private User $memberC;
    private User $targetC;
    private User $ownerD;
    private User $memberD;
    private User $adminAcct;
    private User $unrelated;
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
        });

        $this->ua = User::factory()->create(['role' => 'user']);
        $this->other = User::factory()->create(['role' => 'user']);
        $this->adminAcct = User::factory()->create(['role' => 'admin']);
        $this->unrelated = User::factory()->create(['role' => 'user']);

        $this->orgC = $this->mkOrg('Org C');
        $this->orgD = $this->mkOrg('Org D');
        $this->ownerC = $this->mkUser($this->orgC, 'organization_owner');
        $this->adminC = $this->mkUser($this->orgC, 'organization_admin');
        $this->viewerC = $this->mkUser($this->orgC, 'viewer_read_only');
        $this->memberC = $this->mkUser($this->orgC, 'organization_admin');
        $this->targetC = $this->mkUser($this->orgC, 'estimator');
        $this->ownerD = $this->mkUser($this->orgD, 'organization_owner');
        $this->memberD = $this->mkUser($this->orgD, 'organization_admin');

        $this->logPath = sys_get_temp_dir().'/ua-lifted-'.uniqid().'.log';
        config(['logging.channels.universal_admin' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'info']]);
        Log::forgetChannel('universal_admin');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function variants(): array
    {
        $out = [];
        foreach (['empty', 'other'] as $variant) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["list $variant, $mode"] = [$variant, $mode];
            }
        }

        return $out;
    }

    private function applyVariant(string $variant, string $mode): void
    {
        config(['rbac.universal_admin_user_ids' => $variant === 'empty' ? [] : [$this->other->id]]);
        $this->setMode($mode);
    }

    private function listed(User ...$users): void
    {
        config(['rbac.universal_admin_user_ids' => array_map(fn (User $u) => $u->id, $users)]);
    }

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        return $this->actingAs($u)->withSession($org ? [config('rbac.current_org_session_key') => $org] : [])->json($method, $uri, $data);
    }

    private function page(User $u, string $uri, ?int $org = null)
    {
        return $this->actingAs($u)->withSession($org ? [config('rbac.current_org_session_key') => $org] : [])->get($uri);
    }

    private function delegation(User $from, User $to, Organization $org): Delegation
    {
        return Delegation::create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'org_id' => $org->id, 'granted_by' => $from->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
    }

    private function token(User $owner, string $name = 'tok'): ApiToken
    {
        return ApiToken::create(['user_id' => $owner->id, 'name' => $name, 'token' => hash('sha256', $name.uniqid()), 'is_active' => true]);
    }

    private function bypassRows(?string $reason = null)
    {
        return AuditLog::where('outcome', 'allowed_universal_admin')
            ->when($reason, fn ($q) => $q->where('reason', $reason))->get();
    }

    private function liftedRows()
    {
        return AuditLog::whereIn('reason', ['universal_org_delete', 'universal_ownership_transfer', 'universal_delegation_revoke', 'universal_api_token_revoke'])->get();
    }

    private function logged(string $reason): bool
    {
        return is_file($this->logPath) && str_contains((string) file_get_contents($this->logPath), '"reason":"'.$reason.'"');
    }

    private function ownerCount(Organization $org): int
    {
        return DB::table('user_org_roles')->where('org_id', $org->id)
            ->where('role_id', DB::table('roles')->where('slug', 'organization_owner')->value('id'))->count();
    }

    private function ownerIds(Organization $org): array
    {
        return DB::table('user_org_roles')->where('org_id', $org->id)
            ->where('role_id', DB::table('roles')->where('slug', 'organization_owner')->value('id'))
            ->pluck('user_id')->map(fn ($i) => (int) $i)->sort()->values()->all();
    }

    // ---- (a) listed user ---------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_listed_user_deletes_an_org_they_do_not_own(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);

        $this->req($this->ua, 'DELETE', 'org-admin/settings/delete', [], $this->orgC->id)->assertStatus(302);

        $this->assertNull(DB::table('organizations')->where('id', $this->orgC->id)->first());
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->count());
        $this->assertNotNull(DB::table('organizations')->where('id', $this->orgD->id)->first(), 'other orgs untouched');
        $this->assertTrue($this->logged('universal_org_delete'), 'permanent log keeps the reason (the org delete wipes the org audit rows)');
        $this->assertStringContainsString('"org_id":'.$this->orgC->id, (string) file_get_contents($this->logPath));
        $this->assertStringContainsString('"user_id":'.$this->ua->id, (string) file_get_contents($this->logPath));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_org_delete_still_refused_when_the_org_has_projects_and_writes_no_bypass(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $this->mkProject($this->orgC, $this->ownerC, 'Keep me');

        $this->req($this->ua, 'DELETE', 'org-admin/settings/delete', [], $this->orgC->id);

        $this->assertNotNull(DB::table('organizations')->where('id', $this->orgC->id)->first());
        $this->assertSame(0, $this->liftedRows()->count());
        $this->assertFalse($this->logged('universal_org_delete'));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_transfers_ownership_and_sitting_owners_are_replaced(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $second = $this->mkUser($this->orgC, 'organization_owner');

        $this->req($this->ua, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$this->targetC->id], $this->ownerIds($this->orgC), 'only the new owner holds the owner role');
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->where('user_id', $this->ownerC->id)->where('role_id', DB::table('roles')->where('slug', 'organization_owner')->value('id'))->count());
        $this->assertSame(1, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->where('user_id', $this->adminC->id)->count(), 'non-owner roles untouched');
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->where('user_id', $second->id)->count(), 'second sitting owner removed too');
        $this->assertSame([$this->ownerD->id], $this->ownerIds($this->orgD), 'another org untouched');

        $rows = $this->bypassRows('universal_ownership_transfer');
        $this->assertCount(1, $rows);
        $this->assertSame($this->ua->id, (int) $rows[0]->user_id);
        $this->assertSame($this->orgC->id, (int) $rows[0]->org_id);
        $this->assertTrue($this->logged('universal_ownership_transfer'));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_transfer_to_a_user_already_owner_keeps_them_and_removes_the_rest(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $second = $this->mkUser($this->orgC, 'organization_owner');

        $this->req($this->ua, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $second->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$second->id], $this->ownerIds($this->orgC));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_revokes_another_members_delegation(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $d = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $keep = $this->delegation($this->adminC, $this->targetC, $this->orgC);

        $this->req($this->ua, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id)->assertStatus(302);

        $this->assertFalse((bool) $d->fresh()->is_active);
        $this->assertTrue((bool) $keep->fresh()->is_active, 'only the named delegation');
        $rows = $this->bypassRows('universal_delegation_revoke');
        $this->assertCount(1, $rows);
        $this->assertSame($this->ua->id, (int) $rows[0]->user_id);
        $this->assertSame($this->orgC->id, (int) $rows[0]->org_id);
        $this->assertSame('delegation_and_impersonation', $rows[0]->permission_group);
        $this->assertTrue($this->logged('universal_delegation_revoke'));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_cannot_revoke_a_delegation_of_another_org(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $d = $this->delegation($this->memberD, $this->ownerD, $this->orgD);

        $this->req($this->ua, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id)->assertForbidden();

        $this->assertTrue((bool) $d->fresh()->is_active);
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_revokes_another_members_api_token(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $t = $this->token($this->memberC);
        $keep = $this->token($this->adminC, 'keep');

        $this->req($this->ua, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id)->assertStatus(302);

        $this->assertFalse((bool) $t->fresh()->is_active);
        $this->assertTrue((bool) $keep->fresh()->is_active);
        $rows = $this->bypassRows('universal_api_token_revoke');
        $this->assertCount(1, $rows);
        $this->assertSame($this->ua->id, (int) $rows[0]->user_id);
        $this->assertSame($this->orgC->id, (int) $rows[0]->org_id);
        $this->assertTrue($this->logged('universal_api_token_revoke'));
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_cannot_revoke_tokens_of_non_members_or_other_orgs(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $otherOrgToken = $this->token($this->memberD, 'd');
        $nobodyToken = $this->token($this->other, 'nobody');
        $inactive = $this->mkUser($this->orgC, 'estimator');
        DB::table('user_org_roles')->where('user_id', $inactive->id)->update(['is_active' => false]);
        $inactiveToken = $this->token($inactive, 'inactive');

        foreach ([$otherOrgToken, $nobodyToken, $inactiveToken] as $t) {
            $this->req($this->ua, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id)->assertForbidden();
            $this->assertTrue((bool) $t->fresh()->is_active);
        }
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_a_listed_user_own_revoke_writes_no_bypass_row(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $d = $this->delegation($this->ua, $this->targetC, $this->orgC);
        $t = $this->token($this->ua, 'mine');

        $this->req($this->ua, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id);
        $this->req($this->ua, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id);

        $this->assertFalse((bool) $d->fresh()->is_active);
        $this->assertFalse((bool) $t->fresh()->is_active);
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_a_nonexistent_org_cannot_be_acted_on(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $d = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $t = $this->token($this->memberC);

        $this->req($this->ua, 'DELETE', 'org-admin/settings/delete', [], 999999)->assertForbidden();
        $this->req($this->ua, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], 999999)->assertForbidden();
        $this->req($this->ua, 'DELETE', 'org-admin/delegations/'.$d->id, [], 999999)->assertForbidden();
        $this->req($this->ua, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], 999999)->assertForbidden();

        $this->assertTrue((bool) $d->fresh()->is_active);
        $this->assertTrue((bool) $t->fresh()->is_active);
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_a_unverified_listed_user_is_denied_everywhere(string $mode): void
    {
        $this->setMode($mode);
        $unverified = User::factory()->create(['role' => 'user', 'email_verified_at' => null]);
        $this->listed($unverified);
        $d = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $t = $this->token($this->memberC);

        $this->req($unverified, 'DELETE', 'org-admin/settings/delete', [], $this->orgC->id);
        $this->req($unverified, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id);
        $this->req($unverified, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id);
        $this->req($unverified, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id);

        $this->assertNotNull(DB::table('organizations')->where('id', $this->orgC->id)->first());
        $this->assertSame([$this->ownerC->id], $this->ownerIds($this->orgC));
        $this->assertTrue((bool) $d->fresh()->is_active);
        $this->assertTrue((bool) $t->fresh()->is_active);
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_a_staff_notes_visible_to_listed_user(string $mode): void
    {
        $this->setMode($mode);
        $q = $this->mkQuote($this->est, $this->p1, ['staff_notes' => 'secret note']);
        $this->listed($this->ua);

        $this->assertSame('secret note', $this->req($this->ua, 'GET', "quotes/{$q}/details", [], $this->orgA->id)->assertOk()->json('quote.staff_notes'));
        $this->assertSame('secret note', $this->req($this->est, 'GET', "quotes/{$q}/details", [], $this->orgA->id)->assertOk()->json('quote.staff_notes'));
    }

    // ---- (b) no regression for everybody else --------------------------------------------------------

    #[DataProvider('variants')]
    public function test_b_non_listed_actors_cannot_delete_or_transfer_an_org_they_do_not_own(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $actors = ['org admin' => [$this->adminC, $this->orgC], 'viewer' => [$this->viewerC, $this->orgC],
            'owner of another org' => [$this->ownerD, $this->orgC], 'role=admin account' => [$this->adminAcct, $this->orgC],
            'user without any role' => [$this->unrelated, $this->orgC]];

        foreach ($actors as $label => [$actor, $org]) {
            $resp = $this->req($actor, 'DELETE', 'org-admin/settings/delete', [], $org->id);
            if ($actor !== $this->ownerD) {
                $resp->assertForbidden();
            }
            $resp = $this->req($actor, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $org->id);
            if ($actor !== $this->ownerD) {
                $resp->assertForbidden();
            }
            $this->assertNotNull(DB::table('organizations')->where('id', $org->id)->first(), $label);
            $this->assertSame([$this->ownerC->id], $this->ownerIds($org), $label);
        }
        $this->assertSame(0, $this->bypassRows()->count());
        $this->assertFalse(is_file($this->logPath) && filesize($this->logPath) > 0);
    }

    #[DataProvider('variants')]
    public function test_b_the_real_owner_can_still_transfer_and_delete(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $second = $this->mkUser($this->orgC, 'organization_owner');

        $this->req($this->ownerC, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id)->assertStatus(302);
        $this->assertEqualsCanonicalizing([$second->id, $this->targetC->id], $this->ownerIds($this->orgC), 'only the acting owner is removed, as before');
        $this->assertSame(0, $this->liftedRows()->count(), 'a real owner is never logged as a platform-admin override');

        $this->req($this->targetC, 'DELETE', 'org-admin/settings/delete', [], $this->orgC->id)->assertStatus(302);
        $this->assertNull(DB::table('organizations')->where('id', $this->orgC->id)->first());
        $this->assertSame(0, $this->bypassRows()->count());
    }

    #[DataProvider('variants')]
    public function test_b_non_listed_cannot_revoke_another_users_delegation_or_token(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $d = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $t = $this->token($this->memberC);

        foreach ([$this->ownerC, $this->adminC, $this->viewerC, $this->ownerD, $this->adminAcct, $this->unrelated] as $actor) {
            $this->req($actor, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id)->assertForbidden();
            $this->req($actor, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id)->assertForbidden();
        }
        $this->assertTrue((bool) $d->fresh()->is_active);
        $this->assertTrue((bool) $t->fresh()->is_active);
        $this->assertSame(0, $this->bypassRows()->count());
    }

    #[DataProvider('variants')]
    public function test_b_own_revoke_still_works_for_non_listed(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $d = $this->delegation($this->ownerC, $this->targetC, $this->orgC);
        $t = $this->token($this->ownerC);

        $this->req($this->ownerC, 'DELETE', 'org-admin/delegations/'.$d->id, [], $this->orgC->id)->assertStatus(302);
        $this->req($this->ownerC, 'DELETE', 'org-admin/api-tokens/'.$t->id, [], $this->orgC->id)->assertStatus(302);

        $this->assertFalse((bool) $d->fresh()->is_active);
        $this->assertFalse((bool) $t->fresh()->is_active);
        $this->assertSame(0, $this->bypassRows()->count());
    }

    #[DataProvider('variants')]
    public function test_b_indexes_list_only_own_entries_and_show_no_extra_markup(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $mine = $this->delegation($this->ownerC, $this->targetC, $this->orgC);
        $theirs = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $myToken = $this->token($this->ownerC, 'mine');
        $theirToken = $this->token($this->memberC, 'theirs');

        $r = $this->page($this->ownerC, 'org-admin/delegations', $this->orgC->id)->assertOk();
        $this->assertSame([$mine->id], $r->viewData('granted')->pluck('id')->all());
        $r->assertDontSee('<small class="text-muted d-block">Granted by', false);

        $r = $this->page($this->ownerC, 'org-admin/api-tokens', $this->orgC->id)->assertOk();
        $this->assertSame([$myToken->id], $r->viewData('tokens')->pluck('id')->all());
        $r->assertDontSee('<small class="text-muted d-block">Owner:', false);

        foreach ([$this->adminC, $this->viewerC] as $u) {
            $resp = $this->page($u, 'org-admin/settings', $this->orgC->id);
            if ($resp->getStatusCode() === 200) {
                $resp->assertDontSee('data-bs-target="#deleteOrgModal"', false)->assertDontSee('data-bs-target="#transferModal"', false);
            }
        }
        $this->page($this->ownerC, 'org-admin/settings', $this->orgC->id)->assertOk()->assertSee('data-bs-target="#deleteOrgModal"', false)->assertSee('data-bs-target="#transferModal"', false);
        $this->assertSame(0, $this->bypassRows()->count());
    }

    #[DataProvider('variants')]
    public function test_b_staff_notes_stay_hidden_from_non_author_non_listed_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $q = $this->mkQuote($this->est, $this->p1, ['staff_notes' => 'secret note']);

        $this->assertSame('secret note', $this->req($this->est, 'GET', "quotes/{$q}/details", [], $this->orgA->id)->assertOk()->json('quote.staff_notes'));
        foreach ([$this->owner] as $u) {
            $this->assertNull($this->req($u, 'GET', "quotes/{$q}/details", [], $this->orgA->id)->assertOk()->json('quote.staff_notes'));
        }
        $this->assertSame(0, $this->bypassRows()->count());
    }

    // ---- (c) views for listed users -----------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_c_views_render_for_listed_user_with_extra_controls(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $d = $this->delegation($this->memberC, $this->targetC, $this->orgC);
        $mine = $this->delegation($this->ua, $this->targetC, $this->orgC);
        $t = $this->token($this->memberC, 'theirs');
        $this->token($this->ua, 'mine');
        $this->token($this->memberD, 'other-org');

        $this->page($this->ua, 'org-admin/settings', $this->orgC->id)->assertOk()->assertSee('data-bs-target="#deleteOrgModal"', false)->assertSee('data-bs-target="#transferModal"', false);

        $r = $this->page($this->ua, 'org-admin/delegations', $this->orgC->id)->assertOk()->assertSee('<small class="text-muted d-block">Granted by', false);
        $this->assertEqualsCanonicalizing([$d->id, $mine->id], $r->viewData('granted')->pluck('id')->all());

        $r = $this->page($this->ua, 'org-admin/api-tokens', $this->orgC->id)->assertOk()->assertSee('<small class="text-muted d-block">Owner:', false);
        $this->assertEqualsCanonicalizing(['theirs', 'mine'], $r->viewData('tokens')->pluck('name')->all(), 'own plus members only, never another org');
    }

    // ---- (d) transfer to a user with a pre-existing owner row ----------------------------------------------

    private function ownerRoleId(): int
    {
        return (int) DB::table('roles')->where('slug', 'organization_owner')->value('id');
    }

    private function activeOwnerIds(Organization $org): array
    {
        return DB::table('user_org_roles')->where('org_id', $org->id)->where('role_id', $this->ownerRoleId())->where('is_active', true)
            ->pluck('user_id')->map(fn ($i) => (int) $i)->sort()->values()->all();
    }

    private function inactiveOwnerRow(User $u, Organization $org): void
    {
        $this->assignRole($u, $org, 'organization_owner', false);
    }

    #[DataProvider('modes')]
    public function test_d_real_owner_transfer_reactivates_an_inactive_owner_row(string $mode): void
    {
        $this->setMode($mode);
        $this->inactiveOwnerRow($this->targetC, $this->orgC);

        $this->req($this->ownerC, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$this->targetC->id], $this->activeOwnerIds($this->orgC));
        $this->assertSame([$this->targetC->id], $this->ownerIds($this->orgC), 'old owner row removed, no duplicate row');
        $this->assertSame(0, $this->liftedRows()->count());
    }

    #[DataProvider('modes')]
    public function test_d_listed_transfer_reactivates_an_inactive_owner_row_and_removes_sitting_owners(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $second = $this->mkUser($this->orgC, 'organization_owner');
        $this->inactiveOwnerRow($this->targetC, $this->orgC);

        $this->req($this->ua, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$this->targetC->id], $this->activeOwnerIds($this->orgC));
        $this->assertSame([$this->targetC->id], $this->ownerIds($this->orgC));
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->whereIn('user_id', [$this->ownerC->id, $second->id])->where('role_id', $this->ownerRoleId())->count());
        $this->assertCount(1, $this->bypassRows('universal_ownership_transfer'));
    }

    #[DataProvider('modes')]
    public function test_d_transfer_to_a_member_without_an_owner_row_creates_one_active_row(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->ownerC, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->targetC->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$this->targetC->id], $this->activeOwnerIds($this->orgC));
        $this->assertSame([$this->targetC->id], $this->ownerIds($this->orgC));
        $this->assertSame(1, DB::table('user_org_roles')->where('org_id', $this->orgC->id)->where('user_id', $this->targetC->id)->where('role_id', '!=', $this->ownerRoleId())->count(), 'existing estimator row untouched');
    }

    #[DataProvider('modes')]
    public function test_d_transfer_to_an_existing_active_owner_leaves_that_row_unchanged(string $mode): void
    {
        $this->setMode($mode);
        $second = $this->mkUser($this->orgC, 'organization_owner');
        $stamp = now()->subYear()->startOfSecond();
        DB::table('user_org_roles')->where('user_id', $second->id)->where('org_id', $this->orgC->id)
            ->update(['assigned_by' => $this->adminC->id, 'assigned_at' => $stamp]);
        $before = DB::table('user_org_roles')->where('user_id', $second->id)->where('org_id', $this->orgC->id)->first();

        $this->req($this->ownerC, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $second->email], $this->orgC->id)->assertStatus(302);

        $this->assertSame([$second->id], $this->activeOwnerIds($this->orgC));
        $after = DB::table('user_org_roles')->where('user_id', $second->id)->where('org_id', $this->orgC->id)->get();
        $this->assertCount(1, $after);
        $this->assertSame((int) $this->adminC->id, (int) $after[0]->assigned_by);
        $this->assertSame($before->assigned_at, $after[0]->assigned_at);
        $this->assertSame(1, (int) $after[0]->is_active);
    }
}
