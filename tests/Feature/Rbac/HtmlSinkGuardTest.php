<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;

/**
 * Client audit 2 round 2, S15: another account's name, e-mail, role name or organization name must not run as markup or
 * script in the Team page drawer, the two invite pop-ups, the platform-admin users drawer or the three confirm() dialogs.
 *
 * PHPUnit CANNOT run page JavaScript. This class is two halves that do not need a browser: a static scan of the touched
 * Blade files (no unescaped interpolation into an innerHTML template, the removed dead branches stay removed, every function an
 * onclick names is defined in the same file, every confirm() message goes through @js), and rendered pages with hostile data
 * (the server side of the sink: @json in a script block and @js in an attribute are hex-escaped). The behaviour of the scripts
 * themselves is covered only by the manual browser list in REVIEW.md.
 */
class HtmlSinkGuardTest extends ProjectTestCase
{
    private const HOSTILE_NAME = '</script><img src=x onerror=alert(1)>';
    private const HOSTILE_EMAIL = 'a"b\'c</script>@example.test';
    private const HOSTILE_ORG = 'Org "A" O\'Brien & <b>Co</b>';
    private const HOSTILE_ROLE = 'Esti\'mator "x" </script><i>';

    private const INVITE_VIEWS = ['user/org-admin/index', 'user/org-admin/overview'];
    private const SCRIPT_VIEWS = ['user/org-admin/index', 'user/org-admin/overview', 'admin/rbac/users'];
    private const TOUCHED_VIEWS = [
        'user/org-admin/index', 'user/org-admin/overview', 'admin/rbac/users', 'admin/rbac/organization-show',
        'admin/rbac/service-accounts', 'user/org-admin/api-tokens', 'auth/invite-register', 'emails/org-invite',
    ];

    private function source(string $name): string
    {
        return (string) file_get_contents(resource_path("views/$name.blade.php"));
    }

    private function region(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        $this->assertNotFalse($a, "marker not found: $from");
        $b = strpos($src, $to, $a);
        $this->assertNotFalse($b, "marker not found: $to");

        return substr($src, $a, $b - $a);
    }

    /** @return array<int, string> every ${...} expression in the source, nested ones included */
    private function interpolations(string $src): array
    {
        $found = [];
        $len = strlen($src);
        for ($i = 0; $i < $len - 1; $i++) {
            if ($src[$i] !== '$' || $src[$i + 1] !== '{') {
                continue;
            }
            $depth = 1;
            $j = $i + 2;
            while ($j < $len && $depth > 0) {
                $depth += $src[$j] === '{' ? 1 : ($src[$j] === '}' ? -1 : 0);
                $j++;
            }
            $expr = substr($src, $i + 2, $j - $i - 3);
            $found[] = trim($expr);
            $found = array_merge($found, $this->interpolations($expr));
            $i = $j - 1;
        }

        return $found;
    }

    private function unsafe(string $region, array $allowed): array
    {
        $out = [];
        foreach ($this->interpolations($region) as $expr) {
            if (preg_match('/^escapeHtml\(.*\)$/s', $expr)) {
                continue;
            }
            foreach ($allowed as $pattern) {
                if (preg_match($pattern, $expr)) {
                    continue 2;
                }
            }
            $out[] = $expr;
        }

        return $out;
    }

    // ---- static: the invite pop-ups --------------------------------------------------------------

    public function test_the_overview_pop_up_builds_its_result_without_any_html_string_sink(): void
    {
        $src = $this->source('user/org-admin/overview');

        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function'] as $sink) {
            $this->assertStringNotContainsString($sink, $src, "overview.blade.php uses $sink");
        }
        $this->assertStringContainsString('textContent', $src);
        $this->assertStringContainsString('createTextNode', $src);
    }

    public function test_the_team_page_has_exactly_one_innerhtml_and_it_is_the_escaped_drawer(): void
    {
        $src = $this->source('user/org-admin/index');

        $this->assertSame(1, substr_count($src, 'innerHTML'), 'the invite pop-up must not use innerHTML');
        $this->assertStringContainsString("document.getElementById('drawerBody').innerHTML = `", $src);
        $drawer = $this->region($src, 'function renderDrawer(m)', 'window.showAddRoleForm');
        $this->assertStringContainsString('innerHTML', $drawer);
        foreach (['outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function'] as $sink) {
            $this->assertStringNotContainsString($sink, $src, "index.blade.php uses $sink");
        }
    }

    public function test_no_script_interpolates_the_raw_form_of_an_invite_or_member_value(): void
    {
        $raw = '/\$\{\s*(data\.|email\s*\}|name\s*\}|link\s*\}|m\.name\s*\}|m\.email\s*\}|r\.roleName\s*\}|r\.name\s*\}|r\.category\s*\}|og\.(?!org_type \?|roles\.map\()|d\.)/';

        foreach (self::SCRIPT_VIEWS as $view) {
            $this->assertDoesNotMatchRegularExpression($raw, $this->source($view), "$view interpolates a raw value");
        }
    }

    public function test_the_dead_existing_account_branches_are_gone_from_both_pop_ups(): void
    {
        foreach (self::INVITE_VIEWS as $view) {
            $src = $this->source($view);
            foreach (['confirm_existing', 'confirmExistingAssign', 'user_name', 'copyInviteLink', 'Yes, add them', "'existing'", 'data.existing', 'existingAccount'] as $dead) {
                $this->assertStringNotContainsString($dead, $src, "$view still has $dead");
            }
        }
        $this->assertStringNotContainsString('existingAccount', $this->source('emails/org-invite'));
        $this->assertStringNotContainsString('existingAccount', file_get_contents(app_path('Mail/OrgInviteMail.php')));
    }

    public function test_no_touched_view_prints_unescaped_blade_output(): void
    {
        foreach (self::TOUCHED_VIEWS as $view) {
            $this->assertStringNotContainsString('{!!', $this->source($view), $view);
        }
    }

    // ---- static: the drawers ------------------------------------------------------------------------

    public function test_every_interpolation_in_the_team_drawer_templates_is_escaped_or_a_number_or_a_known_fragment(): void
    {
        $drawer = $this->region($this->source('user/org-admin/index'), 'function renderDrawer(m)', 'window.showAddRoleForm');

        $this->assertGreaterThanOrEqual(8, count($this->interpolations($drawer)), 'the scan found the templates');
        $this->assertSame([], $this->unsafe($drawer, [
            '/^(initials|rolesList|roleOptions|r\.id|m\.roles\.length)$/',
            '/^RBAC\.canManageRoles \? /',
        ]));
        foreach (['m.name', 'm.email', 'r.roleName', 'r.category', 'r.name', 'm.joined'] as $field) {
            $this->assertStringContainsString("escapeHtml($field)", $drawer, $field);
        }
        $this->assertStringContainsString('const initials = escapeHtml(', $drawer);
    }

    public function test_every_interpolation_in_the_platform_admin_drawer_is_escaped_or_a_known_fragment(): void
    {
        $drawer = $this->region($this->source('admin/rbac/users'), 'function openUserDrawer(userId)', 'function closeUserDrawer()');

        $this->assertGreaterThanOrEqual(8, count($this->interpolations($drawer)), 'the scan found the templates');
        $this->assertSame([], $this->unsafe($drawer, [
            '/^u\.id$/',
            '/^og\.org_type \? /',
            '/^og\.roles\.map\(/',
            '/^t\.active \? /',
        ]));
        foreach (['og.org_name', 'og.org_type', 'r.name', 'd.from', 'd.expires'] as $field) {
            $this->assertStringContainsString("escapeHtml($field)", $drawer, $field);
        }
        $this->assertMatchesRegularExpression("/getElementById\('drawerName'\)\.textContent\s*=\s*u\.name/", $drawer);
        $this->assertMatchesRegularExpression("/getElementById\('drawerEmail'\)\.textContent\s*=\s*u\.email/", $drawer);
    }

    public function test_the_generated_drawer_markup_carries_no_inline_event_handler(): void
    {
        $regions = [
            $this->region($this->source('user/org-admin/index'), 'function renderDrawer(m)', 'window.showAddRoleForm'),
            $this->region($this->source('admin/rbac/users'), 'function openUserDrawer(userId)', 'function closeUserDrawer()'),
        ];

        foreach ($regions as $region) {
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $region);
            $this->assertDoesNotMatchRegularExpression('/javascript:/i', $region);
        }
    }

    public function test_escape_html_is_defined_where_the_drawers_use_it_and_escapes_quotes(): void
    {
        foreach (['user/org-admin/index', 'admin/rbac/users'] as $view) {
            $src = $this->source($view);

            $this->assertMatchesRegularExpression('/function escapeHtml\(str\)/', $src, $view);
            $this->assertStringContainsString("'&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#39;'", $src, $view);
        }
    }

    public function test_every_drawer_button_has_a_handler_branch_and_a_defined_target(): void
    {
        $src = $this->source('user/org-admin/index');
        $drawer = $this->region($src, 'function renderDrawer(m)', 'window.showAddRoleForm');

        preg_match_all('/data-action="([a-z-]+)"/', $drawer, $used);
        $this->assertEqualsCanonicalizing(['remove-role', 'show-add-role', 'assign-role', 'hide-add-role'], array_unique($used[1]));

        preg_match_all("/dataset\.action === '([a-z-]+)'\)\s*window\.(\w+)\(/", $src, $branches, PREG_SET_ORDER);
        $handlers = array_column($branches, 2, 1);
        $this->assertSame(
            ['remove-role' => 'removeRole', 'show-add-role' => 'showAddRoleForm', 'assign-role' => 'assignRoleFromDrawer', 'hide-add-role' => 'hideAddRoleForm'],
            $handlers
        );
        foreach ($handlers as $fn) {
            $this->assertMatchesRegularExpression('/window\.'.$fn.'\s*=\s*(async\s+)?function/', $src, "window.$fn is not defined");
        }
        $this->assertStringContainsString("getElementById('drawerBody').addEventListener('click'", $src);
    }

    // ---- static: every function an onclick names exists ----------------------------------------------

    public function test_every_function_named_in_an_onclick_attribute_is_defined_in_the_same_file(): void
    {
        $checked = 0;
        foreach (self::TOUCHED_VIEWS as $view) {
            $src = $this->source($view);
            preg_match_all('/onclick="(?:return\s+)?([A-Za-z_$][\w$.]*)\(/', $src, $m);

            foreach (array_unique($m[1]) as $fn) {
                if (in_array($fn, ['event.stopPropagation', 'confirm'], true)) {
                    continue;
                }
                $defined = preg_match('/window\.'.preg_quote($fn, '/').'\s*=|function\s+'.preg_quote($fn, '/').'\s*\(|(?:const|let|var)\s+'.preg_quote($fn, '/').'\s*=/', $src);
                $this->assertSame(1, $defined, "$view: onclick calls $fn(), which is not defined in that file");
                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(8, $checked);
    }

    public function test_the_invite_pop_up_functions_the_markup_and_the_script_call_exist_in_both_pop_ups(): void
    {
        foreach (self::INVITE_VIEWS as $view) {
            $src = $this->source($view);

            foreach (['resetInviteModal', 'sendInvite', 'openInviteModal', 'doInviteFetch', 'showInviteError', 'showInviteSuccess', 'showInviteForm', 'finishInviteRequest'] as $fn) {
                $this->assertMatchesRegularExpression('/(function\s+'.$fn.'\s*\(|window\.'.$fn.'\s*=)/', $src, "$view: $fn is not defined");
            }
            $this->assertStringContainsString("addEventListener('show.bs.modal', resetInviteModal)", $src, "$view resets on show");
            $this->assertStringContainsString('getOrCreateInstance', $src, "$view must not build a second Bootstrap modal on an open element");
            $popUp = $view === 'user/org-admin/index' ? $this->region($src, 'const INVITE_GENERIC_ERROR', '// ── Role filter') : $src;
            $this->assertStringNotContainsString('alert(', $popUp, "$view: no alert() in the invite pop-up");
        }
    }

    // ---- static: the three confirm() sites -----------------------------------------------------------------

    public function test_every_confirm_message_with_data_goes_through_js_and_no_confirm_embeds_blade_or_template_output(): void
    {
        $sites = 0;
        foreach (self::TOUCHED_VIEWS as $view) {
            foreach (preg_split('/\R/', $this->source($view)) as $line) {
                if (! str_contains($line, 'confirm(')) {
                    continue;
                }
                $this->assertStringNotContainsString('{{', $line, "$view: confirm( with Blade output: $line");
                $this->assertStringNotContainsString('${', $line, "$view: confirm( with a template: $line");
                $this->assertTrue(
                    str_contains($line, 'confirm(@js(') || (bool) preg_match('/confirm\(\'[^\'{$]*\'\)/', $line),
                    "$view: confirm( with an unprotected message: $line"
                );
                $sites += str_contains($line, 'confirm(@js(') ? 1 : 0;
            }
        }

        $this->assertSame(3, $sites, 'the three platform-admin / token confirm() sites');
        foreach ([
            'admin/rbac/organization-show' => "@js('Remove ' . \$assignment->role->name . ' from ' . \$member->name . '?')",
            'admin/rbac/service-accounts' => "@js('Revoke token ' . \$token->name . '?')",
            'user/org-admin/api-tokens' => "@js('Revoke token ' . \$token->name . '? This cannot be undone.')",
        ] as $view => $call) {
            $this->assertStringContainsString("onclick=\"return confirm($call)\"", $this->source($view), $view);
        }
    }

    // ---- rendered: @json in a script block --------------------------------------------------------------------------

    private function hex(string $code): string
    {
        return chr(92).'u'.$code;
    }

    private function hexJson(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    private function teamPage(User $viewer, ?int $orgId = null): string
    {
        return $this->actingAs($viewer)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->get('org-admin')->assertOk()->getContent();
    }

    public function test_the_team_page_hex_escapes_a_hostile_member_organization_and_role_name_inside_its_script(): void
    {
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $member = $this->mkUser($this->orgA, 'procurement_coordinator');
        $baseline = substr_count($this->teamPage($owner), '</script>');

        User::where('id', $member->id)->update(['name' => self::HOSTILE_NAME, 'email' => self::HOSTILE_EMAIL]);
        Organization::where('id', $this->orgA->id)->update(['name' => self::HOSTILE_ORG]);
        DB::table('roles')->where('slug', 'procurement_coordinator')->update(['name' => self::HOSTILE_ROLE]);
        DB::table('roles')->where('slug', 'estimator')->update(['name' => self::HOSTILE_ROLE.' E']);

        $html = $this->teamPage($owner);

        $this->assertStringContainsString('name: '.$this->hexJson(self::HOSTILE_NAME), $html);
        $this->assertStringContainsString('"'.$this->hex('003C').'\/script'.$this->hex('003E').$this->hex('003C').'img src=x onerror=alert(1)'.$this->hex('003E').'"', $html, 'the hex-escaped form');
        $this->assertStringContainsString('email: '.$this->hexJson(self::HOSTILE_EMAIL), $html);
        $this->assertStringContainsString('orgName: '.$this->hexJson(self::HOSTILE_ORG), $html);
        $this->assertStringContainsString('roleName: '.$this->hexJson(self::HOSTILE_ROLE), $html);
        $this->assertStringContainsString('name: '.$this->hexJson(self::HOSTILE_ROLE.' E'), $html, 'the role list of the add-role dropdown');
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<i>', $html, 'the role name is not markup anywhere on the page');
        $this->assertSame($baseline, substr_count($html, '</script>'), 'no extra closing script tag came out of the data');
    }

    public function test_the_overview_page_does_not_let_a_hostile_organization_name_out_of_its_markup(): void
    {
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        Organization::where('id', $this->orgA->id)->update(['name' => self::HOSTILE_ORG]);
        $member = $this->mkUser($this->orgA, 'estimator');
        User::where('id', $member->id)->update(['name' => self::HOSTILE_NAME]);

        $html = $this->actingAs($owner)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->get('org-admin/overview')->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Co</b>', $html);
    }

    public function test_the_platform_admin_users_page_hex_escapes_hostile_account_and_organization_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = $this->mkUser($this->orgA, 'estimator');
        $baseline = substr_count($this->actingAs($admin)->get('admin/rbac/users')->assertOk()->getContent(), '</script>');
        User::where('id', $member->id)->update(['name' => self::HOSTILE_NAME, 'email' => self::HOSTILE_EMAIL]);
        Organization::where('id', $this->orgA->id)->update(['name' => self::HOSTILE_ORG]);
        DB::table('roles')->where('slug', 'estimator')->update(['name' => self::HOSTILE_ROLE]);

        $html = $this->actingAs($admin)->get('admin/rbac/users')->assertOk()->getContent();

        foreach ([self::HOSTILE_NAME, self::HOSTILE_EMAIL, self::HOSTILE_ORG, self::HOSTILE_ROLE] as $value) {
            $this->assertStringContainsString($this->hexJson($value), $html, $value);
        }
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertSame($baseline, substr_count($html, '</script>'));
    }

    // ---- rendered: @js in an attribute -------------------------------------------------------------------------------------

    private function confirmArgument(string $html, string $needle): string
    {
        $this->assertSame(1, preg_match('/onclick="return confirm\((.*?)\)"/s', substr($html, (int) strpos($html, $needle) - 400, 900), $m), "no confirm() around $needle");

        return $m[1];
    }

    private function assertSafeConfirmArgument(string $argument, string $expected, string $label): void
    {
        $this->assertSame($expected, $argument, $label);
        $this->assertSame(2, substr_count($argument, "'"), "$label: only the two string delimiters may be a quote");
        foreach (['"', '<', '>', '&'] as $char) {
            $this->assertStringNotContainsString($char, $argument, "$label contains a raw $char");
        }
    }

    public function test_the_platform_admin_member_confirm_keeps_an_apostrophe_and_markup_in_a_name_inside_the_string(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = $this->mkUser($this->orgA, 'estimator');
        $name = "O'Brien \"Q\" <b>x</b> & Co";
        User::where('id', $member->id)->update(['name' => $name]);
        $roleName = (string) DB::table('roles')->where('slug', 'estimator')->value('name');

        $html = $this->actingAs($admin)->get(route('admin.rbac.organizations.show', $this->orgA))->assertOk()->getContent();

        $this->assertSafeConfirmArgument(
            $this->confirmArgument($html, 'O'.$this->hex('0027').'Brien'),
            Js::from("Remove $roleName from $name?")->toHtml(),
            'organization-show'
        );
        $this->assertStringContainsString('O'.$this->hex('0027').'Brien', $html);
        $this->assertStringNotContainsString("O'Brien \"Q\"", $html);
    }

    public function test_the_service_account_and_token_page_confirms_keep_an_apostrophe_in_a_token_name_inside_the_string(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $name = "CI 'prod' \"x\" <script>alert(1)</script> & co";
        DB::table('api_tokens')->insert([
            'user_id' => $owner->id, 'name' => $name, 'token' => hash('sha256', 'secret'), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = $this->actingAs($admin)->get('admin/rbac/service-accounts')->assertOk()->getContent();
        $this->assertSafeConfirmArgument($this->confirmArgument($service, 'CI '.$this->hex('0027').'prod'.$this->hex('0027')), Js::from("Revoke token $name?")->toHtml(), 'service-accounts');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $service);

        $page = $this->actingAs($owner)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->get('org-admin/api-tokens')->assertOk()->getContent();
        $this->assertSafeConfirmArgument($this->confirmArgument($page, 'CI '.$this->hex('0027').'prod'.$this->hex('0027')), Js::from("Revoke token $name? This cannot be undone.")->toHtml(), 'api-tokens');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
    }
}
