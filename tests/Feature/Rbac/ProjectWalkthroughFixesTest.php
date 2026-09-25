<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Browser-walkthrough fixes: P1 project delete with estimates, P2 parent project in the estimate payloads,
 * P3 add-member dropdown, P4 RFQ create connections link. Every touched page is rendered at least once so a
 * Blade compile or route-name error fails here.
 */
class ProjectWalkthroughFixesTest extends ProjectTestCase
{
    private const HINT = 'All organization members are already in this project';
    private const BLOCKED = 'Delete or move the estimates in this project first.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMode('enforce');
    }

    private function freshProject(string $name = 'Fresh'): int
    {
        $id = $this->mkProject($this->orgA, $this->est, $name);
        $this->member($id, $this->est, $this->orgA);

        return $id;
    }

    private function asJson(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->json($method, $uri, $data);
    }

    private function deleteButtonDisabled(string $html): bool
    {
        $this->assertSame(1, preg_match('/<button[^>]*btn-danger[^>]*>\s*Delete Project\s*<\/button>/', $html, $m), 'delete button rendered');

        return (bool) preg_match('/\sdisabled(\s|=|>)/', $m[0]);
    }

    private function addMemberSelect(string $html): string
    {
        $this->assertSame(1, preg_match('/<select name="user_id".*?<\/select>/s', $html, $m), 'add member select rendered');

        return $m[0];
    }

    private function addMemberButtonDisabled(string $html): bool
    {
        $this->assertSame(1, preg_match('/<button[^>]*btn-primary[^>]*>\s*Add to Project\s*<\/button>/', $html, $m), 'add member button rendered');

        return (bool) preg_match('/\sdisabled(\s|=|>)/', $m[0]);
    }

    // ---- P1 ---------------------------------------------------------------------

    public function test_p1_non_json_delete_with_estimates_redirects_with_flash_and_changes_nothing(): void
    {
        $id = $this->freshProject();
        $q = $this->mkQuote($this->est, $id, ['name' => 'Keep me']);

        $this->actingAs($this->est)->delete("projects/$id")
            ->assertRedirect(route('projects.show', $id))
            ->assertSessionHas('error', self::BLOCKED);

        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
        $this->assertNull(DB::table('quotes')->where('id', $q)->value('deleted_at'));
        $this->assertSame($id, (int) DB::table('quotes')->where('id', $q)->value('project_id'));
        $this->assertSame('Keep me', DB::table('quotes')->where('id', $q)->value('name'));
    }

    public function test_p1_the_error_flash_is_shown_on_the_project_page_after_the_redirect(): void
    {
        $id = $this->freshProject();
        $this->mkQuote($this->est, $id);

        $this->actingAs($this->est)->followingRedirects()->delete("projects/$id")
            ->assertOk()->assertSee(self::BLOCKED);
    }

    public function test_p1_json_delete_with_estimates_is_422_with_the_message(): void
    {
        $id = $this->freshProject();
        $this->mkQuote($this->est, $id);

        $this->asJson($this->est, 'DELETE', "projects/$id")->assertStatus(422)->assertJsonFragment(['message' => self::BLOCKED]);
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_p1_delete_without_estimates_still_deletes_and_redirects_to_the_index(): void
    {
        $id = $this->freshProject();

        $this->actingAs($this->est)->delete("projects/$id")
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
        $this->assertNotNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_p1_only_soft_deleted_estimates_do_not_block_and_the_button_is_enabled(): void
    {
        $id = $this->freshProject();
        $this->mkQuote($this->est, $id, ['deleted_at' => now()]);

        $page = $this->actingAs($this->est)->get("projects/$id")->assertOk();
        $this->assertFalse($this->deleteButtonDisabled($page->getContent()));

        $this->actingAs($this->est)->delete("projects/$id")->assertRedirect(route('projects.index'));
        $this->assertNotNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_p1_below_f_wrong_org_and_non_member_are_still_denied_non_json(): void
    {
        $id = $this->freshProject();
        $this->mkQuote($this->est, $id);
        $eng = $this->mkUser($this->orgA, 'project_engineer');
        $this->member($id, $eng, $this->orgA);

        foreach ([$eng, $this->outsider, $this->stranger] as $u) {
            $r = $this->actingAs($u)->delete("projects/$id");
            $r->assertSessionMissing('success');
            $this->assertNotSame(route('projects.show', $id), $r->headers->get('Location'), 'no blocked-redirect leaks to a denied user');
            $r->assertSessionMissing('error', self::BLOCKED);
        }
        $this->asJson($eng, 'DELETE', "projects/$id")->assertForbidden();
        $this->asJson($this->outsider, 'DELETE', "projects/$id")->assertForbidden();
        $this->asJson($this->stranger, 'DELETE', "projects/$id")->assertForbidden();
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));

        $emptyId = $this->freshProject('Empty');
        $this->member($emptyId, $eng, $this->orgA);
        $this->actingAs($eng)->delete("projects/$emptyId");
        $this->assertNull(DB::table('projects')->where('id', $emptyId)->value('deleted_at'));
    }

    public function test_p1_the_project_page_disables_delete_only_when_estimates_exist(): void
    {
        $with = $this->freshProject('With');
        $this->mkQuote($this->est, $with);
        $without = $this->freshProject('Without');

        $this->assertTrue($this->deleteButtonDisabled($this->actingAs($this->est)->get("projects/$with")->assertOk()->getContent()));
        $this->assertFalse($this->deleteButtonDisabled($this->actingAs($this->est)->get("projects/$without")->assertOk()->getContent()));
    }

    // ---- P2 ---------------------------------------------------------------------

    public function test_p2_list_and_details_carry_the_parent_project(): void
    {
        $list = $this->asJson($this->owner, 'GET', 'quotes/list')->assertOk()->json('quotes');
        $byId = array_column($list, null, 'id');

        $this->assertSame($this->p1, $byId[$this->q1]['parent_project_id']);
        $this->assertSame('P1', $byId[$this->q1]['parent_project_name']);
        $this->assertSame($this->p2, $byId[$this->q3]['parent_project_id']);
        $this->assertSame('P2', $byId[$this->q3]['parent_project_name']);
        $this->assertSame('Q1', $byId[$this->q1]['project_name'], 'project_name keeps its old meaning');

        $d = $this->asJson($this->owner, 'GET', "quotes/{$this->q1}/details")->assertOk();
        $this->assertSame($this->p1, $d->json('quote.parent_project_id'));
        $this->assertSame('P1', $d->json('quote.parent_project_name'));
        $this->assertSame('', $d->json('quote.project_name'), 'project_name is still the quote column, not the project');
    }

    public function test_p2_a_null_project_quote_is_not_visible_so_no_payload_carries_a_null_parent(): void
    {
        $list = array_column($this->asJson($this->owner, 'GET', 'quotes/list')->assertOk()->json('quotes'), null, 'id');
        $this->assertArrayNotHasKey($this->q0, $list);
        foreach ($list as $row) {
            $this->assertNotNull($row['parent_project_id']);
            $this->assertNotNull($row['parent_project_name']);
        }
        $this->asJson($this->owner, 'GET', "quotes/{$this->q0}/details")->assertForbidden();
    }

    public function test_p2_a_user_who_cannot_see_the_quote_never_gets_the_project_name(): void
    {
        foreach ([$this->stranger, $this->outsider] as $u) {
            $body = $this->asJson($u, 'GET', 'quotes/list')->assertOk()->getContent();
            $this->assertStringNotContainsString('"P1"', $body);
            $this->assertStringNotContainsString('parent_project_name":"P1', $body);

            $d = $this->asJson($u, 'GET', "quotes/{$this->q1}/details");
            $d->assertForbidden();
            $this->assertStringNotContainsString('P1', $d->getContent());
        }
        $other = $this->asJson($this->outsider, 'GET', 'quotes/list')->json('quotes');
        $this->assertSame(['P3'], array_column($other, 'parent_project_name'));
    }

    public function test_p2_a_member_of_one_project_does_not_see_another_projects_name(): void
    {
        $body = $this->asJson($this->est, 'GET', 'quotes/list')->assertOk()->getContent();
        $this->assertStringContainsString('P1', $body);
        $this->assertStringNotContainsString('P2', $body);
        $this->asJson($this->est, 'GET', "quotes/{$this->q3}/details")->assertForbidden();
    }

    /**
     * Only the project lookups are counted: the list has a separate, older per-quote quote_items query
     * (calculateTotal) that grows with the quote count and is unrelated to the parent project.
     */
    public function test_p2_the_list_loads_the_parent_projects_in_one_query_regardless_of_quote_count(): void
    {
        $projectSelects = function () {
            $sql = [];
            DB::listen(function ($q) use (&$sql) {
                $s = strtolower($q->sql);
                if (str_starts_with($s, 'select') && preg_match('/from ["`]projects["`]/', $s) && ! preg_match('/from ["`]quotes["`]/', $s)) {
                    $sql[] = $s;
                }
            });
            $this->asJson($this->owner, 'GET', 'quotes/list')->assertOk();

            return $sql;
        };

        $before = $projectSelects();
        foreach (range(1, 6) as $i) {
            $p = $this->mkProject($this->orgA, $this->owner, "Extra $i");
            $this->member($p, $this->owner, $this->orgA);
            $this->mkQuote($this->owner, $p);
            $this->mkQuote($this->owner, $p);
        }
        $after = $projectSelects();

        $this->assertCount(15, $this->asJson($this->owner, 'GET', 'quotes/list')->json('quotes'));
        $this->assertNotEmpty($before);
        $this->assertCount(count($before), $after, 'project queries must not grow with the number of quotes');
    }

    public function test_p2_the_quotes_page_renders_with_the_parent_project_link_helper(): void
    {
        $html = $this->actingAs($this->owner)->get('quotes')->assertOk()->getContent();

        $this->assertStringContainsString('parentProjectLinkHtml', $html);
        $this->assertStringContainsString('PROJECT_SHOW_URL', $html);
        $this->assertStringContainsString(json_encode(route('projects.show', ['project' => '__ID__']), JSON_UNESCAPED_SLASHES), str_replace('\/', '/', $html));
    }

    // ---- P3 ---------------------------------------------------------------------

    public function test_p3_dropdown_excludes_active_members_and_includes_inactive_members_and_non_members(): void
    {
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->super->id)->update(['is_active' => false]);

        $r = $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk();
        $ids = $r->viewData('orgMembers')->pluck('id')->all();

        foreach ([$this->est, $this->viewer, $this->multi, $this->owner] as $activeMember) {
            $this->assertNotContains($activeMember->id, $ids, "active member {$activeMember->id} excluded");
        }
        $this->assertContains($this->super->id, $ids, 'inactive member included');
        $this->assertContains($this->stranger->id, $ids, 'non-member included');
        $this->assertNotContains($this->outsider->id, $ids, 'other org never listed');

        $select = $this->addMemberSelect($r->getContent());
        $this->assertStringContainsString('value="'.$this->super->id.'"', $select);
        $this->assertStringContainsString('value="'.$this->stranger->id.'"', $select);
        $this->assertStringNotContainsString('value="'.$this->est->id.'"', $select);
        $this->assertStringNotContainsString(self::HINT, $select);
        $this->assertStringContainsString('Select a member', $select);
        $this->assertFalse($this->addMemberButtonDisabled($r->getContent()));
    }

    public function test_p3_membership_of_another_project_does_not_exclude_a_user(): void
    {
        $ids = $this->actingAs($this->est)->get("projects/{$this->p1}")->viewData('orgMembers')->pluck('id')->all();
        $this->member($this->p2, $this->stranger, $this->orgA);
        $after = $this->actingAs($this->est)->get("projects/{$this->p1}")->viewData('orgMembers')->pluck('id')->all();

        $this->assertContains($this->stranger->id, $ids);
        $this->assertContains($this->stranger->id, $after);
    }

    public function test_p3_when_everyone_is_already_a_member_the_hint_shows_and_submit_is_disabled(): void
    {
        $id = $this->freshProject('Full');
        $others = DB::table('user_org_roles')->where('org_id', $this->orgA->id)->where('is_active', true)
            ->where('user_id', '!=', $this->est->id)->pluck('user_id')->unique();
        foreach ($others as $uid) {
            $this->member($id, User::findOrFail($uid), $this->orgA);
        }

        $r = $this->actingAs($this->est)->get("projects/$id")->assertOk();
        $this->assertTrue($r->viewData('orgMembers')->isEmpty());
        $this->assertStringContainsString(self::HINT, $this->addMemberSelect($r->getContent()));
        $this->assertTrue($this->addMemberButtonDisabled($r->getContent()));
    }

    public function test_p3_server_side_add_of_an_existing_member_creates_no_duplicate(): void
    {
        $before = DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->count();

        $this->actingAs($this->est)->post("projects/{$this->p1}/members", ['user_id' => $this->viewer->id])
            ->assertSessionHas('success', 'User is already a member of this project.');
        $this->asJson($this->est, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->viewer->id])
            ->assertOk()->assertJsonFragment(['message' => 'User is already a member of this project.']);

        $this->assertSame($before, DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->count());
        $this->assertSame(1, $before);
    }

    public function test_p3_a_user_below_f_gets_no_member_picker(): void
    {
        $r = $this->actingAs($this->viewer)->get("projects/{$this->p1}")->assertOk();
        $this->assertTrue($r->viewData('orgMembers')->isEmpty());
    }

    // ---- P4 ---------------------------------------------------------------------

    public function test_p4_rfq_create_renders_for_a_buyer_without_connections_with_the_connections_link(): void
    {
        $this->assertSame(url('org-admin/connections'), route('org-admin.connections.index'));

        $r = $this->actingAs($this->est)->get(route('rfq.create'));
        $r->assertOk();
        $r->assertSee('No trading partners yet.', false);
        $r->assertSee('href="'.route('org-admin.connections.index').'"', false);
    }
}
