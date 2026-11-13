<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\PermissionMatrix;
use App\Services\Rbac\SodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2 round 2, N01 option B (D1): an Organization Owner is exempt from the two seeded SoD pairs that sit inside
 * the registration bundle (Procurement Manager + Executive Approver, Estimator + Executive Approver) in every organization,
 * not only when alone. The exemption needs an ACTIVE Owner row in THIS org, applies only when both roles of the pair are in
 * config('rbac.onboarding.bundles.buyer_small'), and changes nothing for anyone else or for any other pair. Every case runs
 * in a multi-member org, so the sole-member exemption of SodSoloOrgTest is not what lets it through.
 */
class SodOwnerBundleExemptionTest extends ProjectTestCase
{
    private const EXEMPT = [
        ['procurement_manager', 'executive_approver'],
        ['executive_approver', 'procurement_manager'],
        ['estimator', 'executive_approver'],
        ['executive_approver', 'estimator'],
    ];

    private User $ownerA;
    private User $ownerB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->admin = User::factory()->create(['role' => 'admin']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function exemptPairs(): array
    {
        $out = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (self::EXEMPT as [$held, $incoming]) {
                $out["$held then $incoming $mode"] = [$held, $incoming, $mode];
            }
        }

        return $out;
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function assignVia(User $actor, Organization $org, int $targetId, string $roleSlug)
    {
        return $this->actingAs($actor)
            ->withSession([config('rbac.current_org_session_key') => $org->id])
            ->postJson('org-admin/roles', ['user_id' => $targetId, 'role_id' => $this->roleId($roleSlug)]);
    }

    private function holds(User $u, Organization $org, string $roleSlug): bool
    {
        return DB::table('user_org_roles')
            ->where('user_id', $u->id)->where('org_id', $org->id)->where('role_id', $this->roleId($roleSlug))->where('is_active', true)
            ->exists();
    }

    private function assignedLogs(User $u, Organization $org): array
    {
        return DB::table('role_assignment_logs')->where('target_user_id', $u->id)->where('org_id', $org->id)->where('action', 'assigned')
            ->orderBy('id')->pluck('role_id')->map(fn ($id) => (int) $id)->all();
    }

    private function conflicts(User $u, Organization $org, string $incoming): array
    {
        return app(SodService::class)->conflictsFor($u->id, $org->id, $this->roleId($incoming));
    }

    // ---- the Owner, through the Team page --------------------------------------------------------------

    #[DataProvider('exemptPairs')]
    public function test_an_owner_in_a_team_org_holds_the_bundle_pair_in_either_order(string $first, string $second, string $mode): void
    {
        $this->setMode($mode);
        $owner = $this->mkUser($this->orgA, 'organization_owner');

        $this->assignVia($this->ownerA, $this->orgA, $owner->id, $first)->assertOk()->assertJson(['success' => true]);
        $this->assignVia($this->ownerA, $this->orgA, $owner->id, $second)->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($this->holds($owner, $this->orgA, $first));
        $this->assertTrue($this->holds($owner, $this->orgA, $second));
        $this->assertTrue($this->holds($owner, $this->orgA, 'organization_owner'));
        $this->assertSame([$this->roleId($first), $this->roleId($second)], $this->assignedLogs($owner, $this->orgA), 'both grants are logged with who did it');
        $this->assertSame($this->ownerA->id, (int) DB::table('role_assignment_logs')->where('target_user_id', $owner->id)->where('role_id', $this->roleId($second))->value('performed_by'));
    }

    #[DataProvider('modes')]
    public function test_the_owner_can_give_himself_the_pair_in_a_team_org(string $mode): void
    {
        $this->setMode($mode);

        $this->assignVia($this->ownerA, $this->orgA, $this->ownerA->id, 'estimator')->assertOk();
        $this->assignVia($this->ownerA, $this->orgA, $this->ownerA->id, 'executive_approver')->assertOk();
        $this->assignVia($this->ownerA, $this->orgA, $this->ownerA->id, 'procurement_manager')->assertOk();

        foreach (['organization_owner', 'estimator', 'executive_approver', 'procurement_manager'] as $slug) {
            $this->assertTrue($this->holds($this->ownerA, $this->orgA, $slug), $slug);
        }
        $this->assertGreaterThan(1, DB::table('user_org_roles')->where('org_id', $this->orgA->id)->where('is_active', true)->distinct()->count('user_id'));
    }

    #[DataProvider('modes')]
    public function test_the_owner_role_granted_after_the_first_role_gives_the_same_result(string $mode): void
    {
        $this->setMode($mode);
        $member = $this->mkUser($this->orgA, 'estimator');

        $this->assignVia($this->ownerA, $this->orgA, $member->id, 'executive_approver')->assertStatus(422)->assertJson(['sod_conflict' => true]);
        $this->assignVia($this->ownerA, $this->orgA, $member->id, 'organization_owner')->assertOk();
        $this->assignVia($this->ownerA, $this->orgA, $member->id, 'executive_approver')->assertOk();

        $this->assertTrue($this->holds($member, $this->orgA, 'estimator'));
        $this->assertTrue($this->holds($member, $this->orgA, 'executive_approver'));
    }

    // ---- everyone else stays refused ---------------------------------------------------------------------

    public static function refusedHolders(): array
    {
        $out = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (['organization_admin', 'viewer_read_only', 'project_manager'] as $baseRole) {
                foreach (self::EXEMPT as [$held, $incoming]) {
                    $out["$baseRole holding $held, incoming $incoming $mode"] = [$baseRole, $held, $incoming, $mode];
                }
            }
        }

        return $out;
    }

    #[DataProvider('refusedHolders')]
    public function test_a_member_who_is_not_an_owner_is_still_refused_the_bundle_pair(string $baseRole, string $held, string $incoming, string $mode): void
    {
        $this->setMode($mode);
        $member = $this->mkUser($this->orgA, $baseRole);
        if (! $this->holds($member, $this->orgA, $held)) {
            $this->assignRole($member, $this->orgA, $held);
        }

        $r = $this->assignVia($this->ownerA, $this->orgA, $member->id, $incoming)->assertStatus(422);

        $this->assertTrue($r->json('sod_conflict'));
        $this->assertStringContainsString('SoD conflict: this user already holds', (string) $r->json('message'));
        $this->assertFalse($this->holds($member, $this->orgA, $incoming));
        $this->assertTrue($this->holds($member, $this->orgA, $held), 'nothing is revoked');
    }

    // ---- the whole matrix --------------------------------------------------------------------------------------

    public function test_for_an_owner_in_a_multi_member_org_exactly_the_four_bundle_directions_are_exempt_and_twelve_stay_refused(): void
    {
        $rules = DB::table('sod_conflict_rules')->where('is_active', true)->get();
        $this->assertCount(8, $rules, 'the eight seeded rules');
        $exempt = 0;
        $refused = 0;

        foreach ($rules as $rule) {
            $a = Role::findOrFail($rule->role_id_a);
            $b = Role::findOrFail($rule->role_id_b);

            foreach ([[$a, $b], [$b, $a]] as [$held, $incoming]) {
                $owner = $this->mkUser($this->orgA, 'organization_owner');
                $this->assignRole($owner, $this->orgA, $held->slug);

                $conflicts = $this->conflicts($owner, $this->orgA, $incoming->slug);

                if (in_array([$held->slug, $incoming->slug], self::EXEMPT, true)) {
                    $this->assertSame([], $conflicts, "$held->slug then $incoming->slug is exempt for an Owner");
                    $exempt++;
                } else {
                    $this->assertSame([$held->name], $conflicts, "$held->slug then $incoming->slug stays refused for an Owner");
                    $refused++;
                }
            }
        }

        $this->assertSame([4, 12], [$exempt, $refused]);
    }

    public function test_for_a_member_who_is_not_an_owner_no_direction_is_exempt(): void
    {
        $rules = DB::table('sod_conflict_rules')->where('is_active', true)->get();

        foreach ($rules as $rule) {
            $a = Role::findOrFail($rule->role_id_a);
            $b = Role::findOrFail($rule->role_id_b);

            foreach ([[$a, $b], [$b, $a]] as [$held, $incoming]) {
                $member = $this->mkUser($this->orgA, 'viewer_read_only');
                $this->assignRole($member, $this->orgA, $held->slug);

                $this->assertSame([$held->name], $this->conflicts($member, $this->orgA, $incoming->slug), "$held->slug then $incoming->slug");
            }
        }
    }

    #[DataProvider('modes')]
    public function test_an_owner_is_still_refused_every_pair_outside_the_bundle_through_the_team_page(string $mode): void
    {
        $this->setMode($mode);

        foreach ([['requisitioner', 'executive_approver'], ['ap_invoice_clerk', 'executive_approver'], ['procurement_manager', 'financial_admin']] as [$held, $incoming]) {
            $owner = $this->mkUser($this->orgA, 'organization_owner');
            $this->assignRole($owner, $this->orgA, $held);

            $r = $this->assignVia($this->ownerA, $this->orgA, $owner->id, $incoming)->assertStatus(422);

            $this->assertTrue($r->json('sod_conflict'), "$held then $incoming");
            $this->assertFalse($this->holds($owner, $this->orgA, $incoming), "$held then $incoming");
        }
    }

    // ---- scope of the exemption ---------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_an_inactive_owner_row_gives_no_exemption(string $mode): void
    {
        $this->setMode($mode);
        $former = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($former, $this->orgA, 'organization_owner', false);

        $r = $this->assignVia($this->ownerA, $this->orgA, $former->id, 'executive_approver')->assertStatus(422);

        $this->assertTrue($r->json('sod_conflict'));
        $this->assertFalse($this->holds($former, $this->orgA, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_being_an_owner_of_another_org_gives_no_exemption_here(string $mode): void
    {
        $this->setMode($mode);
        $traveller = $this->mkUser($this->orgB, 'organization_owner');
        $this->assignRole($traveller, $this->orgA, 'estimator');

        $r = $this->assignVia($this->ownerA, $this->orgA, $traveller->id, 'executive_approver')->assertStatus(422);

        $this->assertTrue($r->json('sod_conflict'));
        $this->assertFalse($this->holds($traveller, $this->orgA, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_the_owner_exemption_is_per_org_in_both_directions(string $mode): void
    {
        $this->setMode($mode);
        $both = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($both, $this->orgB, 'estimator');
        $this->assignRole($both, $this->orgA, 'estimator');

        $this->assignVia($this->ownerB, $this->orgB, $both->id, 'executive_approver')->assertStatus(422);
        $this->assignVia($this->ownerA, $this->orgA, $both->id, 'executive_approver')->assertOk();

        $this->assertFalse($this->holds($both, $this->orgB, 'executive_approver'));
        $this->assertTrue($this->holds($both, $this->orgA, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_a_mixed_hold_is_refused_and_names_only_the_roles_outside_the_bundle(string $mode): void
    {
        $this->setMode($mode);
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($owner, $this->orgA, 'estimator');
        $this->assignRole($owner, $this->orgA, 'requisitioner');

        $this->assertSame(['Requisitioner'], $this->conflicts($owner, $this->orgA, 'executive_approver'));

        $r = $this->assignVia($this->ownerA, $this->orgA, $owner->id, 'executive_approver')->assertStatus(422);

        $this->assertStringContainsString('already holds Requisitioner.', (string) $r->json('message'));
        $this->assertStringNotContainsString('Estimator', (string) $r->json('message'));
        $this->assertFalse($this->holds($owner, $this->orgA, 'executive_approver'));
    }

    // ---- the bundle comes from config -----------------------------------------------------------------------------

    public function test_the_exempt_roles_are_the_configured_buyer_small_bundle_not_a_fixed_list(): void
    {
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($owner, $this->orgA, 'estimator');
        $this->assignRole($owner, $this->orgA, 'procurement_manager');

        $this->assertSame([], $this->conflicts($owner, $this->orgA, 'executive_approver'), 'default bundle');

        config(['rbac.onboarding.bundles.buyer_small' => ['organization_owner', 'estimator', 'executive_approver']]);
        $this->assertSame(['Procurement Manager'], $this->conflicts($owner, $this->orgA, 'executive_approver'), 'Procurement Manager left the bundle; Estimator did not');

        $soloEstimator = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($soloEstimator, $this->orgA, 'estimator');
        $this->assertSame([], $this->conflicts($soloEstimator, $this->orgA, 'executive_approver'), 'Estimator + Executive Approver is still inside the configured bundle');

        config(['rbac.onboarding.bundles.buyer_small' => []]);
        $this->assertSame(['Estimator'], $this->conflicts($soloEstimator, $this->orgA, 'executive_approver'), 'an empty bundle exempts nothing');

        config(['rbac.onboarding.bundles.buyer_small' => ['organization_owner', 'procurement_manager', 'estimator', 'executive_approver', 'requisitioner']]);
        $this->assertSame([], $this->conflicts($this->ownerWith('requisitioner'), $this->orgA, 'executive_approver'), 'a role added to the bundle is exempt');
    }

    private function ownerWith(string $slug): User
    {
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($owner, $this->orgA, $slug);

        return $owner;
    }

    public function test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants(): void
    {
        $covered = array_values(array_diff(config('rbac.onboarding.bundles.buyer_small'), ['organization_owner']));
        $this->assertNotEmpty($covered, 'an empty bundle would pass this test without comparing anything');

        $grants = fn (string $slug): array => DB::table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permission_groups as pg', 'pg.id', '=', 'rp.permission_group_id')
            ->where('r.slug', $slug)->pluck('rp.access_level', 'pg.slug')->all();

        $owner = $grants('organization_owner');
        $this->assertNotEmpty($owner, 'the Owner role has seeded grants');

        $uncovered = [];
        foreach ($covered as $slug) {
            $own = $grants($slug);
            $this->assertNotEmpty($own, "$slug has no seeded grants, so there is nothing to compare");

            foreach ($own as $group => $level) {
                if (! isset($owner[$group]) || PermissionMatrix::rank($owner[$group]) > PermissionMatrix::rank($level)) {
                    $uncovered[] = "$slug holds $group at $level, organization_owner holds ".($owner[$group] ?? 'no row');
                }
            }
        }

        $this->assertSame([], $uncovered, 'the Owner exemption adds a grant the Owner role does not carry');
    }

    // ---- the other callers of the same rule ---------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_invite_acceptance_lets_an_owner_of_a_team_org_take_the_pair_and_refuses_an_organization_admin(string $mode): void
    {
        $this->setMode($mode);
        $ownerInvitee = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($ownerInvitee, $this->orgA, 'estimator');
        $adminInvitee = $this->mkUser($this->orgA, 'organization_admin');
        $this->assignRole($adminInvitee, $this->orgA, 'estimator');

        $ownerToken = $this->invite($ownerInvitee, 'executive_approver', 'tok-owner');
        $adminToken = $this->invite($adminInvitee, 'executive_approver', 'tok-admin');

        $this->actingAs($ownerInvitee)->post("invite/$ownerToken/accept")->assertRedirect(route('user.dashboard'));
        $this->assertTrue($this->holds($ownerInvitee, $this->orgA, 'executive_approver'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $ownerToken)->value('used_at'));

        $this->actingAs($adminInvitee)->post("invite/$adminToken/accept")->assertRedirect(route('invite.review', $adminToken))->assertSessionHas('error');
        $this->assertFalse($this->holds($adminInvitee, $this->orgA, 'executive_approver'));
        $this->assertNull(DB::table('org_invites')->where('token', $adminToken)->value('used_at'), 'the invite stays usable');
    }

    private function invite(User $invitee, string $roleSlug, string $token): string
    {
        DB::table('org_invites')->insert([
            'token' => $token, 'email' => $invitee->email, 'name' => 'Invitee', 'org_id' => $this->orgA->id,
            'role_id' => $this->roleId($roleSlug), 'invited_by' => $this->ownerA->id, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    public function test_the_platform_admin_screen_gives_the_same_answers(): void
    {
        $ownerTarget = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($ownerTarget, $this->orgA, 'estimator');
        $adminTarget = $this->mkUser($this->orgA, 'organization_admin');
        $this->assignRole($adminTarget, $this->orgA, 'estimator');

        $this->actingAs($this->admin)->post(route('admin.rbac.roles.assign', $this->orgA), [
            'user_id' => $ownerTarget->id, 'role_id' => $this->roleId('executive_approver'),
        ])->assertSessionHas('success', 'Role assigned.');
        $this->actingAs($this->admin)->post(route('admin.rbac.roles.assign', $this->orgA), [
            'user_id' => $adminTarget->id, 'role_id' => $this->roleId('executive_approver'),
        ])->assertSessionHas('error');

        $this->assertTrue($this->holds($ownerTarget, $this->orgA, 'executive_approver'));
        $this->assertFalse($this->holds($adminTarget, $this->orgA, 'executive_approver'));
    }

    // ---- A05 and auto-approval are untouched ---------------------------------------------------------------------------

    private function orderTables(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('order_number')->unique();
            $t->string('name')->nullable();
            $t->string('project_title')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('company')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postcode')->nullable();
            $t->text('notes')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('tax_rate', 5, 3)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->unsignedBigInteger('rejected_by')->nullable();
            $t->text('approval_note')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->timestamps();
        });
    }

    /** @return array{0: Organization, 1: User, 2: User} a team org whose Owner took Procurement Manager and Executive Approver through the Team page, and a second approver */
    private function teamOrgWithBundleOwner(string $mode): array
    {
        $this->setMode($mode);
        $org = $this->mkOrg('Org C');
        $owner = $this->mkUser($org, 'organization_owner');
        $this->mkUser($org, 'viewer_read_only');
        $mitch = $this->mkUser($org, 'executive_approver');

        $this->assignVia($owner, $org, $owner->id, 'procurement_manager')->assertOk();
        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertOk();
        $this->assertTrue($this->holds($owner, $org, 'procurement_manager'));
        $this->assertTrue($this->holds($owner, $org, 'executive_approver'));

        return [$org, $owner, $mitch];
    }

    private function pendingOrder(User $submitter, Organization $org): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => $submitter->id, 'org_id' => $org->id, 'order_number' => 'ORD-'.uniqid(),
            'name' => 'Order', 'status' => 'pending_approval', 'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function act(User $actor, Organization $org, string $verb, int $orderId)
    {
        return $this->actingAs($actor)->withSession([config('rbac.current_org_session_key') => $org->id])->postJson("orders/$orderId/$verb", ['note' => 'ok']);
    }

    #[DataProvider('modes')]
    public function test_the_owner_with_the_pair_still_cannot_approve_his_own_order_while_another_approver_exists(string $mode): void
    {
        [$org, $owner] = $this->teamOrgWithBundleOwner($mode);
        $this->orderTables();
        $order = $this->pendingOrder($owner, $org);

        $this->act($owner, $org, 'approve', $order)->assertForbidden()->assertJson(['message' => 'You cannot approve your own order while another approver is available.']);

        $row = DB::table('orders')->where('id', $order)->first();
        $this->assertSame('pending_approval', $row->status);
        $this->assertNull($row->approved_by);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_the_owner_with_the_pair_as_the_only_approver_is_auto_approved_with_an_audit_row(string $mode): void
    {
        [$org, $owner, $mitch] = $this->teamOrgWithBundleOwner($mode);
        DB::table('user_org_roles')->where('user_id', $mitch->id)->update(['is_active' => false]);
        $this->orderTables();
        $sellerOrg = $this->mkOrg('Seller Org');
        $seller = $this->mkUser($sellerOrg, 'sales_rep');
        DB::table('org_relationships')->insert([
            'from_org_id' => $org->id, 'to_org_id' => $sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $project = $this->mkProject($org, $owner, 'Project C');
        $rfq = (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $org->id, 'project_id' => $project, 'created_by' => $owner->id,
            'title' => 'RFQ', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $sellerOrg->id, 'status' => 'responded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_responses')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $sellerOrg->id, 'created_by' => $seller->id,
            'total_price' => 250, 'status' => 'selected', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->withSession([config('rbac.current_org_session_key') => $org->id])->post("rfq/$rfq/convert")->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertSame(['processing', $owner->id], [$order->status, (int) $order->approved_by]);
        $rows = AuditLog::where('outcome', 'auto_approved')->get();
        $this->assertCount(1, $rows);
        $this->assertSame([$owner->id, $org->id, 'sole_approver order '.$order->order_number], [(int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->reason]);
    }
}
