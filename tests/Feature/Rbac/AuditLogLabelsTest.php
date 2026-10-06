<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\RbacSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogLabelsTest extends ProjectTestCase
{
    private User $ownerA;
    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    private function row(string $outcome, string $uri = 'GET some/route'): void
    {
        AuditLog::create([
            'user_id' => $this->ownerA->id, 'org_id' => $this->orgA->id, 'method' => 'GET', 'route_uri' => $uri,
            'permission_group' => 'user_management', 'required_level' => 'F', 'outcome' => $outcome,
        ]);
    }

    private function page(): string
    {
        return $this->actingAs($this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();
    }

    public function test_audit_mode_banner_and_would_block_badges(): void
    {
        $this->setMode('audit');
        $this->row('would_block');

        $html = $this->page();

        $this->assertStringContainsString('<strong>Audit mode:</strong>', $html);
        $this->assertStringContainsString('Would Block</span>', $html);
        $this->assertStringNotContainsString('Enforcement is ON', $html);
        $this->assertStringNotContainsString('>Blocked</span>', $html);
    }

    public function test_enforce_mode_with_no_batches_says_all_route_groups_and_labels_blocked(): void
    {
        $this->setMode('enforce');
        $this->row('blocked');

        $html = $this->page();

        $this->assertStringContainsString('Enforcement is ON for all route groups', $html);
        $this->assertStringNotContainsString('Audit mode:', $html);
        $this->assertStringContainsString('>Blocked</span>', $html);
        $this->assertStringNotContainsString('Would Block</span>', $html);
    }

    public function test_enforce_mode_with_batches_names_them_and_says_others_are_only_logged(): void
    {
        $this->setMode('enforce');
        config(['rbac.enforce_batches' => ['read', 'admin']]);
        $this->row('blocked');
        $this->row('would_block', 'GET other/route');

        $html = $this->page();

        $this->assertStringContainsString('Enforcement is ON for batches read, admin', $html);
        $this->assertStringContainsString('requests in other batches are only logged as Would Block', $html);
        $this->assertStringNotContainsString('Enforcement is ON for all route groups', $html);
        $this->assertStringContainsString('>Blocked</span>', $html);
        $this->assertStringContainsString('Would Block</span>', $html);
    }

    public function test_universal_admin_row_reads_platform_admin(): void
    {
        $this->row('allowed_universal_admin');

        $html = $this->page();

        $this->assertStringContainsString('>Platform admin</span>', $html);
        $this->assertStringNotContainsString('Would Block</span>', $html);
        $this->assertStringNotContainsString('>Blocked</span>', $html);
    }

    public function test_unknown_outcome_shows_its_raw_value(): void
    {
        $this->row('weird_outcome_xyz');

        $html = $this->page();

        $this->assertStringContainsString('>weird_outcome_xyz</span>', $html);
        $this->assertStringNotContainsString('Would Block</span>', $html);
    }

    public function test_each_row_gets_its_own_label(): void
    {
        $this->row('blocked');
        $this->row('would_block');
        $this->row('allowed_universal_admin');
        $this->row('mystery');

        $html = $this->page();

        $this->assertSame(1, substr_count($html, '>Blocked</span>'));
        $this->assertSame(1, substr_count($html, 'Would Block</span>'));
        $this->assertSame(1, substr_count($html, '>Platform admin</span>'));
        $this->assertSame(1, substr_count($html, '>mystery</span>'));
    }

    public function test_page_is_still_denied_without_audit_and_logging_read(): void
    {
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->actingAs($this->viewer)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
                ->getJson('org-admin/audit-log')->assertForbidden();
            $this->actingAs($this->outsider)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
                ->getJson('org-admin/audit-log')->assertForbidden();
        }
    }

    private function navHtml(User $u): string
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/my-roles')->assertOk()->getContent();
    }

    public function test_navbar_links_appear_for_a_listed_verified_user(): void
    {
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $html = $this->navHtml($this->ua);

        $this->assertStringContainsString('Platform admin panel', $html);
        $this->assertStringContainsString('>Enforcement mode</a>', $html);
        $this->assertStringContainsString('admin/rbac/enforcement', $html);
    }

    public function test_navbar_links_are_absent_for_normal_users(): void
    {
        foreach ([[], [$this->ua->id]] as $list) {
            config(['rbac.universal_admin_user_ids' => $list]);
            foreach ([$this->ownerA, $this->viewer, $this->multi] as $u) {
                $html = $this->navHtml($u);
                $this->assertStringNotContainsString('Platform admin panel', $html);
                $this->assertStringNotContainsString('>Enforcement mode</a>', $html);
            }
        }
    }

    public function test_navbar_links_are_absent_for_a_listed_but_unverified_user(): void
    {
        DB::table('users')->where('id', $this->ua->id)->update(['email_verified_at' => null]);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->assertFalse(\App\Support\Rbac\UniversalAdmin::is($this->ua->id));
        $r = $this->actingAs($this->ua)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->get('org-admin/my-roles');
        $this->assertNotSame(200, $r->getStatusCode());
        $this->assertStringNotContainsString('Platform admin panel', (string) $r->getContent());
    }
}
