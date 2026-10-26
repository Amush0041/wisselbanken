<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, same class as O10 (decision G1): ownership can only be handed to, and a delegation only be granted
 * to, an active member of the acting organization. Ownership transfer also stops revealing whether an e-mail has an account.
 */
class OwnershipDelegationMemberGuardTest extends ProjectTestCase
{
    private const NOT_OWNER_MEMBER = 'The new owner must already be an active member of this organization.';
    private const NOT_DELEGATE_MEMBER = 'Only active members of your organization can be delegates.';

    private User $ownerA;
    private User $memberA;
    private User $removed;
    private User $outsiderOwner;
    private User $ua;
    private $orgC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->memberA = $this->mkUser($this->orgA, 'estimator');
        $this->removed = $this->mkUser($this->orgA, 'estimator');
        DB::table('user_org_roles')->where('user_id', $this->removed->id)->update(['is_active' => false]);
        $this->orgC = $this->mkOrg('Org C');
        $this->outsiderOwner = $this->mkUser($this->orgC, 'organization_owner');
        $this->ua = User::factory()->create(['role' => 'user']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function as(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function transfer(User $actor, string $email, ?int $orgId = null)
    {
        return $this->as($actor, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $email], $orgId);
    }

    private function delegate(User $actor, int $toUserId)
    {
        return $this->as($actor, 'POST', 'org-admin/delegations', [
            'to_user_id' => $toUserId,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ]);
    }

    private function ownerRoleId(): int
    {
        return (int) Role::where('slug', 'organization_owner')->value('id');
    }

    private function ownerRows(int $orgId): array
    {
        return DB::table('user_org_roles')->where('org_id', $orgId)->where('role_id', $this->ownerRoleId())->where('is_active', true)
            ->orderBy('user_id')->pluck('user_id')->map(fn ($i) => (int) $i)->all();
    }

    private function roleTable(): array
    {
        return DB::table('user_org_roles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function assertBlockedControllerDenial(User $actor, string $uri): void
    {
        $row = AuditLog::where('user_id', $actor->id)->where('route_uri', $uri)->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame('controller_denied', $row->reason);
    }

    // ---- 3a: ownership transfer --------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_owner_can_transfer_ownership_to_an_active_member(string $mode): void
    {
        $this->setMode($mode);

        $this->transfer($this->ownerA, $this->memberA->email)->assertStatus(302);

        $this->assertSame([$this->memberA->id], $this->ownerRows($this->orgA->id));
        $this->assertSame(0, AuditLog::where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_ownership_is_refused_for_anyone_who_is_not_an_active_member(string $mode): void
    {
        $this->setMode($mode);
        $before = $this->roleTable();

        foreach ([
            'owns another org, no row here' => $this->outsiderOwner->email,
            'all rows here inactive' => $this->removed->email,
            'member of org B only' => $this->outsider->email,
        ] as $label => $email) {
            $this->transfer($this->ownerA, $email)->assertForbidden()->assertJson(['message' => self::NOT_OWNER_MEMBER]);
            $this->assertSame($before, $this->roleTable(), $label);
            $this->assertBlockedControllerDenial($this->ownerA, 'org-admin/settings/transfer');
        }

        $this->assertSame([$this->ownerA->id], $this->ownerRows($this->orgA->id));
    }

    #[DataProvider('modes')]
    public function test_an_unknown_e_mail_is_indistinguishable_from_a_non_member(string $mode): void
    {
        $this->setMode($mode);
        $before = $this->roleTable();

        $unknown = $this->transfer($this->ownerA, 'nobody-here@example.test');
        $known = $this->transfer($this->ownerA, $this->outsiderOwner->email);

        $unknown->assertForbidden();
        $known->assertForbidden();
        $this->assertSame($known->json(), $unknown->json());
        $unknown->assertSessionHasNoErrors();
        $this->assertSame($before, $this->roleTable());
    }

    #[DataProvider('modes')]
    public function test_a_malformed_e_mail_is_still_a_validation_error(string $mode): void
    {
        $this->setMode($mode);

        $this->transfer($this->ownerA, 'not-an-email')->assertStatus(422);
        $this->transfer($this->ownerA, '')->assertStatus(422);
    }

    #[DataProvider('modes')]
    public function test_transferring_to_yourself_is_still_refused(string $mode): void
    {
        $this->setMode($mode);

        $this->transfer($this->ownerA, $this->ownerA->email)->assertStatus(422);

        $this->assertSame([$this->ownerA->id], $this->ownerRows($this->orgA->id));
    }

    #[DataProvider('modes')]
    public function test_a_non_owner_is_still_refused_before_the_member_rule(string $mode): void
    {
        $this->setMode($mode);
        $admin = $this->mkUser($this->orgA, 'organization_admin');

        $this->transfer($admin, $this->memberA->email)->assertForbidden();

        $this->assertSame([$this->ownerA->id], $this->ownerRows($this->orgA->id));
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_may_hand_ownership_to_a_non_member_and_it_is_logged(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->transfer($this->ua, $this->outsiderOwner->email)->assertStatus(302);

        $this->assertSame([$this->outsiderOwner->id], $this->ownerRows($this->orgA->id));
        $reasons = AuditLog::where('outcome', 'allowed_universal_admin')->where('user_id', $this->ua->id)->pluck('reason')->all();
        $this->assertContains('universal_transfer_non_member', $reasons);
        $this->assertContains('universal_ownership_transfer', $reasons);
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_gets_no_bypass_for_an_unknown_e_mail(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->transfer($this->ua, 'nobody-here@example.test')->assertForbidden();

        $this->assertSame([$this->ownerA->id], $this->ownerRows($this->orgA->id));
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_handing_ownership_to_a_member_leaves_no_non_member_row(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->transfer($this->ua, $this->memberA->email)->assertStatus(302);

        $this->assertSame(0, AuditLog::where('reason', 'universal_transfer_non_member')->count());
    }

    // ---- 3b: delegation ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_owner_can_delegate_to_an_active_member(string $mode): void
    {
        $this->setMode($mode);

        $this->delegate($this->ownerA, $this->memberA->id)->assertStatus(302);

        $this->assertSame(1, DB::table('delegations')->where('from_user_id', $this->ownerA->id)->where('to_user_id', $this->memberA->id)->where('org_id', $this->orgA->id)->count());
        $this->assertSame(0, AuditLog::where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_delegation_to_a_non_member_is_refused_and_writes_nothing(string $mode): void
    {
        $this->setMode($mode);

        foreach ([
            'owns another org, no row here' => $this->outsiderOwner,
            'all rows here inactive' => $this->removed,
            'member of org B only' => $this->outsider,
        ] as $label => $target) {
            $this->delegate($this->ownerA, $target->id)->assertForbidden()->assertJson(['message' => self::NOT_DELEGATE_MEMBER]);
            $this->assertBlockedControllerDenial($this->ownerA, 'org-admin/delegations');
            $this->assertSame(0, DB::table('delegations')->count(), $label);
        }
    }

    #[DataProvider('modes')]
    public function test_a_non_member_never_evaluates_as_the_principal(string $mode): void
    {
        $this->setMode($mode);
        $perm = app(\App\Services\Rbac\PermissionService::class);

        $this->delegate($this->ownerA, $this->outsiderOwner->id)->assertForbidden();

        $this->assertFalse($perm->checkPermission($this->outsiderOwner->id, $this->orgA->id, 'user_management', 'R'));
    }

    #[DataProvider('modes')]
    public function test_a_caller_without_the_delegation_level_is_still_refused(string $mode): void
    {
        $this->setMode($mode);

        $this->delegate($this->viewer, $this->memberA->id)->assertForbidden();

        $this->assertSame(0, DB::table('delegations')->count());
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_gets_no_member_exemption_for_delegations(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->delegate($this->ua, $this->outsiderOwner->id)->assertForbidden();
        $this->delegate($this->ua, $this->memberA->id)->assertStatus(302);

        $this->assertSame(1, DB::table('delegations')->count());
    }
}
