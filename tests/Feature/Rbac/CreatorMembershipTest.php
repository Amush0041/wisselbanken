<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Delegation;
use App\Models\RfqRecipient;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2 round 2, M12 (D2 i): whoever creates an estimate (store, createFromList, duplicate) or an RFQ in a project
 * is an active member of that project afterwards, through ProjectMember::enrol (one row, one project_member_logs row performed
 * by the creator). Nothing is written for a creator who is not an active member of the project's organization (a listed
 * universal admin acting in another org), before authorisation, or when the creation fails. Project creation already enrolled
 * the creator and is pinned here as the control.
 *
 * Fixture (ProjectTestCase): P1 and P2 in org A, P3 in org B; est is a member of P1 only; owner (an estimator) of P1 and P2.
 */
class CreatorMembershipTest extends ProjectTestCase
{
    /** organization_owner in A, enrolled nowhere (sees every project through W10) */
    private User $ownerNm;
    /** organization_admin in A: estimate_management R and quote_rfq_management R, sees every project through W10 */
    private User $adminNm;
    /** project_manager in A, member of P1: quote_rfq_management F */
    private User $pm;
    /** listed universal admin with no role in A */
    private User $ua;
    private $sellerOrg;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });

        $this->ownerNm = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminNm = $this->mkUser($this->orgA, 'organization_admin');
        $this->pm = $this->mkUser($this->orgA, 'project_manager');
        $this->member($this->p1, $this->pm, $this->orgA);
        $this->ua = User::factory()->create(['role' => 'user']);

        $this->sellerOrg = $this->mkOrg('Seller Org');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[1, $this->orgA, $this->owner], [2, $this->orgB, $this->outsider]] as [$id, $org, $by]) {
            DB::table('customers')->insert(['id' => $id, 'user_id' => $by->id, 'org_id' => $org->id, 'company_name' => "C$id", 'created_at' => now(), 'updated_at' => now()]);
        }

        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function sites(): array
    {
        $out = [];
        foreach (['store', 'from-list', 'duplicate', 'rfq'] as $site) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["$site $mode"] = [$site, $mode];
            }
        }

        return $out;
    }

    // ---- helpers ----------------------------------------------------------------

    private function listFor(User $u): int
    {
        $id = DB::table('saved_lists')->where('user_id', $u->id)->value('id');

        return (int) ($id ?: DB::table('saved_lists')->insertGetId(['user_id' => $u->id, 'name' => 'List', 'created_at' => now(), 'updated_at' => now()]));
    }

    private function quoteIn(int $projectId): int
    {
        $id = DB::table('quotes')->where('project_id', $projectId)->value('id');

        return (int) ($id ?: $this->mkQuote($this->owner, $projectId, ['name' => 'Original']));
    }

    private function create(string $site, User $u, int $projectId, array $over = [], ?int $orgId = null)
    {
        $client = $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id]);

        return match ($site) {
            'store' => $client->postJson("projects/$projectId/quotes", $over + ['customer_id' => 1]),
            'from-list' => $client->postJson("projects/$projectId/quotes/create-from-list/".($over['list'] ?? $this->listFor($u))),
            'duplicate' => $client->postJson('quotes/'.($over['quote'] ?? $this->quoteIn($projectId)).'/duplicate'),
            'rfq' => $client->postJson('rfq', $over + ['project_id' => $projectId, 'title' => 'RFQ', 'seller_org_ids' => [$this->sellerOrg->id]]),
        };
    }

    private function assertAccepted($response, string $site): void
    {
        $site === 'rfq' ? $response->assertRedirect(route('rfq.index')) : $response->assertSuccessful();
    }

    private function created(string $site): int
    {
        return DB::table($site === 'rfq' ? 'rfq_requests' : 'quotes')->count();
    }

    private function memberRows(int $projectId, User $u): array
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->get()->all();
    }

    private function logRows(int $projectId, ?User $u = null): array
    {
        $q = DB::table('project_member_logs')->where('project_id', $projectId);

        return ($u ? $q->where('target_user_id', $u->id) : $q)->orderBy('id')->get()->all();
    }

    private function totals(): array
    {
        return [DB::table('project_members')->count(), DB::table('project_member_logs')->count()];
    }

    private function memberFor(string $site): User
    {
        return $site === 'rfq' ? $this->pm : $this->est;
    }

    // ---- a creator who is already a member --------------------------------------------------------

    #[DataProvider('sites')]
    public function test_a_member_creator_gets_no_extra_row_and_no_extra_log_row(string $site, string $mode): void
    {
        $this->setMode($mode);
        $actor = $this->memberFor($site);
        $existing = $this->memberRows($this->p1, $actor);
        $this->assertCount(1, $existing);
        $totals = $this->totals();
        $made = $this->created($site);

        $this->assertAccepted($this->create($site, $actor, $this->p1), $site);

        $this->assertSame($made + 1, $this->created($site));
        $this->assertEquals($existing, $this->memberRows($this->p1, $actor), 'the membership row is not touched');
        $this->assertSame($totals, $this->totals());
    }

    // ---- an org-wide creator who is not a member ---------------------------------------------------

    #[DataProvider('sites')]
    public function test_an_owner_who_is_not_a_member_becomes_one_with_one_added_row_performed_by_himself(string $site, string $mode): void
    {
        $this->setMode($mode);
        $this->assertSame([], $this->memberRows($this->p2, $this->ownerNm));
        [$members, $logs] = $this->totals();
        $others = DB::table('project_members')->where('project_id', $this->p2)->orderBy('id')->get()->all();
        $made = $this->created($site);

        $this->assertAccepted($this->create($site, $this->ownerNm, $this->p2), $site);

        $this->assertSame($made + 1, $this->created($site));
        $rows = $this->memberRows($this->p2, $this->ownerNm);
        $this->assertCount(1, $rows);
        $this->assertSame([1, $this->orgA->id, $this->ownerNm->id], [(int) $rows[0]->is_active, (int) $rows[0]->org_id, (int) $rows[0]->granted_by]);
        $this->assertSame($this->p2, (int) $rows[0]->project_id);
        $this->assertNull($rows[0]->quote_id);
        $log = $this->logRows($this->p2, $this->ownerNm);
        $this->assertCount(1, $log);
        $this->assertSame(['added', $this->orgA->id, $this->ownerNm->id], [$log[0]->action, (int) $log[0]->org_id, (int) $log[0]->performed_by]);
        $this->assertSame([$members + 1, $logs + 1], $this->totals());
        $this->assertEquals($others, DB::table('project_members')->where('project_id', $this->p2)->where('user_id', '!=', $this->ownerNm->id)->orderBy('id')->get()->all(), 'nobody else is touched');
    }

    #[DataProvider('sites')]
    public function test_creating_twice_leaves_one_row_and_one_log_row(string $site, string $mode): void
    {
        $this->setMode($mode);

        $this->assertAccepted($this->create($site, $this->ownerNm, $this->p2), $site);
        $this->assertAccepted($this->create($site, $this->ownerNm, $this->p2), $site);

        $this->assertCount(1, $this->memberRows($this->p2, $this->ownerNm));
        $this->assertCount(1, $this->logRows($this->p2, $this->ownerNm));
    }

    #[DataProvider('modes')]
    public function test_a_duplicate_by_an_owner_who_did_not_write_the_original_enrols_the_duplicator_not_the_author(string $mode): void
    {
        $this->setMode($mode);
        $original = $this->quoteIn($this->p2);
        $author = (int) DB::table('quotes')->where('id', $original)->value('user_id');
        $this->assertNotSame($this->ownerNm->id, $author);
        $authorRow = DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $author)->first();
        $authorLogs = count($this->logRows($this->p2, User::findOrFail($author)));

        $id = $this->create('duplicate', $this->ownerNm, $this->p2, ['quote' => $original])->assertOk()->json('quote_id');

        $this->assertSame($this->ownerNm->id, (int) DB::table('quotes')->where('id', $id)->value('user_id'));
        $this->assertCount(1, $this->memberRows($this->p2, $this->ownerNm));
        $this->assertSame($this->ownerNm->id, (int) $this->logRows($this->p2, $this->ownerNm)[0]->performed_by);
        $this->assertEquals($authorRow, DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $author)->first());
        $this->assertSame($authorLogs, count($this->logRows($this->p2, User::findOrFail($author))));
    }

    #[DataProvider('sites')]
    public function test_a_removed_owner_who_creates_again_is_reactivated_with_exactly_one_reactivated_log_row(string $site, string $mode): void
    {
        $this->setMode($mode);
        $rowId = $this->member($this->p2, $this->ownerNm, $this->orgA, false);

        $this->assertAccepted($this->create($site, $this->ownerNm, $this->p2), $site);

        $rows = $this->memberRows($this->p2, $this->ownerNm);
        $this->assertCount(1, $rows);
        $this->assertSame([$rowId, 1], [(int) $rows[0]->id, (int) $rows[0]->is_active]);
        $log = $this->logRows($this->p2, $this->ownerNm);
        $this->assertCount(1, $log);
        $this->assertSame(['reactivated', $this->ownerNm->id], [$log[0]->action, (int) $log[0]->performed_by]);
    }

    #[DataProvider('modes')]
    public function test_the_membership_org_is_the_projects_org_even_when_the_creators_first_role_is_elsewhere(string $mode): void
    {
        $this->setMode($mode);
        $traveller = $this->mkUser($this->orgB, 'viewer_read_only');
        $this->assignRole($traveller, $this->orgA, 'organization_owner');

        $this->create('store', $traveller, $this->p2)->assertOk();

        $row = $this->memberRows($this->p2, $traveller)[0];
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame($this->orgA->id, (int) $this->logRows($this->p2, $traveller)[0]->org_id);
    }

    // ---- nobody is enrolled without authorisation ----------------------------------------------------

    #[DataProvider('sites')]
    public function test_an_organization_admin_below_the_level_is_refused_and_no_row_is_written(string $site, string $mode): void
    {
        $this->setMode($mode);
        $totals = $this->totals();
        $made = $this->created($site);

        $this->create($site, $this->adminNm, $this->p2)->assertStatus($site === 'duplicate' && $mode === 'audit' ? 404 : 403);

        $this->assertSame($made, $this->created($site));
        $this->assertSame([], $this->memberRows($this->p2, $this->adminNm));
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('sites')]
    public function test_a_project_the_creator_cannot_see_is_refused_and_no_row_is_written(string $site, string $mode): void
    {
        $this->setMode($mode);
        $totals = $this->totals();
        $made = $this->created($site);

        $this->assertGreaterThanOrEqual(400, $this->create($site, $this->memberFor($site), $this->p2)->getStatusCode(), 'a member of P1 only');
        $this->assertSame([], $this->memberRows($this->p2, $this->memberFor($site)));

        $this->assertGreaterThanOrEqual(400, $this->create($site, $this->ownerNm, $this->p3, [], $this->orgA->id)->getStatusCode(), 'another organization');
        $this->assertSame([], $this->memberRows($this->p3, $this->ownerNm));

        $this->assertSame($made, $this->created($site));
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('sites')]
    public function test_a_soft_deleted_project_is_refused_and_no_row_is_written(string $site, string $mode): void
    {
        $this->setMode($mode);
        $quote = $this->quoteIn($this->p2);
        $list = $this->listFor($this->ownerNm);
        DB::table('projects')->where('id', $this->p2)->update(['deleted_at' => now()]);
        $totals = $this->totals();
        $made = $this->created($site);

        $this->create($site, $this->ownerNm, $this->p2, ['quote' => $quote, 'list' => $list])->assertStatus($site === 'rfq' ? 422 : 404);

        $this->assertSame($made, $this->created($site));
        $this->assertSame([], $this->memberRows($this->p2, $this->ownerNm));
        $this->assertSame($totals, $this->totals());
    }

    // ---- nobody is enrolled when the creation fails ------------------------------------------------------

    public static function invalidRequests(): array
    {
        $out = [];
        foreach (['audit', 'enforce'] as $mode) {
            $out["store without a customer $mode"] = ['store', ['customer_id' => ''], 422, $mode];
            $out["store with another org's customer $mode"] = ['store', ['customer_id' => 2], 404, $mode];
            $out["from-list with a list that is not the callers $mode"] = ['from-list', ['list' => 'foreign'], 404, $mode];
            $out["duplicate of a quote that does not exist $mode"] = ['duplicate', ['quote' => 999999], 404, $mode];
            $out["rfq without a title $mode"] = ['rfq', ['title' => ''], 422, $mode];
            $out["rfq to an organization that is not a partner $mode"] = ['rfq', ['seller_org_ids' => [999]], 422, $mode];
            $out["rfq without recipients $mode"] = ['rfq', ['seller_org_ids' => []], 422, $mode];
        }

        return $out;
    }

    #[DataProvider('invalidRequests')]
    public function test_a_failing_request_leaves_no_membership_and_no_log_row(string $site, array $over, int $status, string $mode): void
    {
        $this->setMode($mode);
        if (($over['list'] ?? null) === 'foreign') {
            $over['list'] = $this->listFor($this->est);
        }
        $totals = $this->totals();
        $made = $this->created($site);

        $this->create($site, $this->ownerNm, $this->p2, $over)->assertStatus($status);

        $this->assertSame($made, $this->created($site));
        $this->assertSame([], $this->memberRows($this->p2, $this->ownerNm));
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('modes')]
    public function test_a_failing_rfq_rolls_the_enrolment_back_with_the_rfq(string $mode): void
    {
        $this->setMode($mode);
        RfqRecipient::creating(fn () => throw new \RuntimeException('recipient insert failed'));
        $totals = $this->totals();

        $this->create('rfq', $this->ownerNm, $this->p2)->assertStatus(500);

        $this->assertSame(0, DB::table('rfq_requests')->count());
        $this->assertSame(0, DB::table('rfq_recipients')->count());
        $this->assertSame([], $this->memberRows($this->p2, $this->ownerNm), 'enrolment and RFQ commit or roll back together');
        $this->assertSame([], $this->logRows($this->p2, $this->ownerNm));
        $this->assertSame($totals, $this->totals());
    }

    // ---- a universal admin who is not in the org -------------------------------------------------------------

    #[DataProvider('sites')]
    public function test_a_listed_universal_admin_acting_in_another_org_creates_without_a_row_or_an_error(string $site, string $mode): void
    {
        $this->setMode($mode);
        $totals = $this->totals();
        $made = $this->created($site);

        $this->assertAccepted($this->create($site, $this->ua, $this->p2), $site);

        $this->assertSame($made + 1, $this->created($site));
        $this->assertSame([], $this->memberRows($this->p2, $this->ua));
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('sites')]
    public function test_a_universal_admin_whose_only_role_row_in_the_org_is_inactive_gets_no_row_either(string $site, string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ua, $this->orgA, 'organization_owner', false);
        $totals = $this->totals();

        $this->assertAccepted($this->create($site, $this->ua, $this->p2), $site);

        $this->assertSame([], $this->memberRows($this->p2, $this->ua));
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('sites')]
    public function test_a_universal_admin_who_belongs_to_a_different_org_gets_no_row_in_this_one(string $site, string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ua, $this->orgB, 'organization_owner');
        $totals = $this->totals();

        $this->assertAccepted($this->create($site, $this->ua, $this->p2), $site);

        $this->assertSame([], $this->memberRows($this->p2, $this->ua));
        $this->assertSame([], DB::table('project_members')->where('user_id', $this->ua->id)->get()->all());
        $this->assertSame($totals, $this->totals());
    }

    #[DataProvider('sites')]
    public function test_a_universal_admin_with_an_active_role_in_the_org_is_enrolled_like_anyone_else(string $site, string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ua, $this->orgA, 'organization_owner');

        $this->assertAccepted($this->create($site, $this->ua, $this->p2), $site);

        $this->assertCount(1, $this->memberRows($this->p2, $this->ua));
        $this->assertSame($this->ua->id, (int) $this->logRows($this->p2, $this->ua)[0]->performed_by);
    }

    // ---- a delegate -------------------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_delegate_creating_for_a_principal_enrols_the_delegate_and_not_the_principal(string $mode): void
    {
        $this->setMode($mode);
        $principal = $this->mkUser($this->orgA, 'project_manager');
        $this->member($this->p2, $principal, $this->orgA);
        Delegation::create([
            'from_user_id' => $principal->id, 'to_user_id' => $this->adminNm->id, 'org_id' => $this->orgA->id, 'granted_by' => $principal->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
        $principalRow = $this->memberRows($this->p2, $principal);
        $principalLogs = $this->logRows($this->p2, $principal);

        $id = $this->create('store', $this->adminNm, $this->p2)->assertOk()->json('quote_id');

        $this->assertSame($this->adminNm->id, (int) DB::table('quotes')->where('id', $id)->value('user_id'), 'the quote records the logged-in delegate');
        $this->assertCount(1, $this->memberRows($this->p2, $this->adminNm), 'the person who created it is the member');
        $this->assertSame($this->adminNm->id, (int) $this->logRows($this->p2, $this->adminNm)[0]->performed_by);
        $this->assertEquals($principalRow, $this->memberRows($this->p2, $principal));
        $this->assertEquals($principalLogs, $this->logRows($this->p2, $principal));
    }

    // ---- project creation, the control ---------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_creating_a_project_still_enrols_the_creator_exactly_once(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->ownerNm, $this->est, $this->adminNm] as $creator) {
            $id = (int) $this->actingAs($creator)->postJson('projects', ['name' => 'By '.$creator->id, 'status' => 'active'])->assertStatus(201)->json('project_id');

            $this->assertCount(1, $this->memberRows($id, $creator));
            $log = $this->logRows($id, $creator);
            $this->assertCount(1, $log);
            $this->assertSame(['added', $creator->id], [$log[0]->action, (int) $log[0]->performed_by]);
        }
    }
}
