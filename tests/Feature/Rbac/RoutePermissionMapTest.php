<?php

namespace Tests\Feature\Rbac;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Lint of config/route_permission_map.php against the registered routes (PLAN D5 rules 1-7).
 * Reads the real config and the real routes; needs no database.
 */
class RoutePermissionMapTest extends TestCase
{
    /** The 10 Phase 3 entries: key => [param kind, param name]. */
    private const PHASE3_ENTRIES = [
        'GET quotes/{id}/details' => ['quote_param', 'id'],
        'GET quotes/{id}/pdf-preview' => ['quote_param', 'id'],
        'GET quotes/{id}/pdf' => ['quote_param', 'id'],
        'POST quotes/{id}/duplicate' => ['quote_param', 'id'],
        'PUT quotes/{id}' => ['quote_param', 'id'],
        'PUT quotes/{id}/editor' => ['quote_param', 'id'],
        'PUT quotes/{quoteId}/items/{itemId}' => ['quote_param', 'quoteId'],
        'DELETE quotes/{id}' => ['quote_param', 'id'],
        'DELETE quotes/{quoteId}/items/{itemId}' => ['quote_param', 'quoteId'],
        'GET projects/{quote}/workspace' => ['quote_param', 'quote'],
    ];

    private const UNSCOPED_QUOTE_GETS = [
        'GET quotes', 'GET quotes/list', 'GET quotes/customers', 'GET quotes/product-variations', 'GET quotes/services',
    ];

    private function map(): array
    {
        return config('route_permission_map');
    }

    /** @return array<string, array{0:string,1:string}> registered "METHOD uri" => [method, uri] (HEAD excluded) */
    private function registered(): array
    {
        $out = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $out["$method {$route->uri()}"] = [$method, $route->uri()];
            }
        }

        return $out;
    }

    private function paramsOf(string $uri): array
    {
        preg_match_all('/\{(\w+)\??\}/', $uri, $m);

        return $m[1];
    }

    private function uriOf(string $key): string
    {
        return explode(' ', $key, 2)[1];
    }

    public function test_no_entry_has_both_project_param_and_quote_param(): void
    {
        foreach ($this->map() as $key => $rule) {
            $this->assertFalse(isset($rule['project_param']) && isset($rule['quote_param']), "$key has both");
        }
    }

    public function test_no_project_param_on_a_quote_uri(): void
    {
        foreach ($this->map() as $key => $rule) {
            if (! isset($rule['project_param'])) {
                continue;
            }
            $uri = $this->uriOf($key);
            $this->assertStringNotContainsString('quotes/', $uri, "$key: project_param on a quotes/ URI");
            $this->assertStringNotContainsString('{quote}', $uri, "$key: project_param on a {quote} URI");
        }
        // No Phase 3 route uses project_param at all; the rule is a guard for Phase 4.
        $this->addToAssertionCount(1);
    }

    public function test_every_param_value_is_a_route_parameter_of_that_uri(): void
    {
        $checked = 0;
        foreach ($this->map() as $key => $rule) {
            foreach (['project_param', 'quote_param'] as $kind) {
                if (! isset($rule[$kind])) {
                    continue;
                }
                $checked++;
                $this->assertContains($rule[$kind], $this->paramsOf($this->uriOf($key)), "$key: $kind '{$rule[$kind]}' is not a parameter of the URI");
            }
        }
        $this->assertGreaterThanOrEqual(10, $checked);
    }

    public function test_every_registered_quote_uri_with_an_id_has_a_quote_param_entry(): void
    {
        $seen = 0;
        foreach ($this->registered() as $key => [$method, $uri]) {
            if (! str_starts_with($uri, 'quotes/') || in_array($key, self::UNSCOPED_QUOTE_GETS, true)) {
                continue;
            }
            $params = $this->paramsOf($uri);
            if (! array_intersect(['id', 'quoteId'], $params)) {
                continue;
            }
            $seen++;
            $this->assertArrayHasKey($key, $this->map(), "$key has no map entry");
            $this->assertArrayHasKey('quote_param', $this->map()[$key], "$key must carry quote_param");
            $this->assertContains($this->map()[$key]['quote_param'], ['id', 'quoteId']);
        }
        $this->assertSame(9, $seen, 'the 9 quote routes with an id');
    }

    public function test_every_quotes_and_projects_route_has_a_map_entry(): void
    {
        $missing = [];
        foreach ($this->registered() as $key => [$method, $uri]) {
            if (! (str_starts_with($uri, 'quotes') || str_starts_with($uri, 'projects/'))) {
                continue;
            }
            if (! isset($this->map()[$key]) && ! isset($this->map()["* $uri"])) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'unmapped quotes/projects routes fail open');
    }

    public function test_the_workspace_route_is_mapped_with_quote_param_quote(): void
    {
        $this->assertArrayHasKey('GET projects/{quote}/workspace', $this->registered());
        $this->assertSame('quote', $this->map()['GET projects/{quote}/workspace']['quote_param'] ?? null);
        $this->assertArrayNotHasKey('project_param', $this->map()['GET projects/{quote}/workspace']);
    }

    public function test_every_project_uri_has_project_param_project(): void
    {
        foreach ($this->map() as $key => $rule) {
            if (str_contains($this->uriOf($key), '{project}')) {
                $this->assertSame('project', $rule['project_param'] ?? null, "$key must have project_param => 'project'");
            }
        }
        foreach ($this->registered() as $key => [$method, $uri]) {
            if (str_contains($uri, '{project}')) {
                $this->assertSame('project', $this->map()[$key]['project_param'] ?? null, "$key (registered) must have project_param => 'project'");
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_the_exact_ten_changed_entries_are_present_and_correct(): void
    {
        $this->assertCount(10, self::PHASE3_ENTRIES);
        foreach (self::PHASE3_ENTRIES as $key => [$kind, $param]) {
            $this->assertArrayHasKey($key, $this->map(), "$key missing");
            $rule = $this->map()[$key];
            $this->assertSame($param, $rule[$kind] ?? null, "$key: $kind");
            $other = $kind === 'quote_param' ? 'project_param' : 'quote_param';
            $this->assertArrayNotHasKey($other, $rule, "$key: must not carry $other");
        }
    }

    public function test_the_ten_entries_keep_their_group_level_and_batch(): void
    {
        $expected = [
            'GET quotes/{id}/details' => ['estimate_management', 'R', 'read'],
            'GET quotes/{id}/pdf-preview' => ['estimate_management', 'R', 'read'],
            'GET quotes/{id}/pdf' => ['estimate_management', 'R', 'read'],
            'POST quotes/{id}/duplicate' => ['estimate_management', 'O', 'write'],
            'PUT quotes/{id}' => ['estimate_management', 'O', 'write'],
            'PUT quotes/{id}/editor' => ['estimate_management', 'O', 'write'],
            'PUT quotes/{quoteId}/items/{itemId}' => ['estimate_management', 'O', 'write'],
            'DELETE quotes/{id}' => ['estimate_management', 'F', 'approve'],
            'DELETE quotes/{quoteId}/items/{itemId}' => ['estimate_management', 'F', 'approve'],
            'GET projects/{quote}/workspace' => ['estimate_management', 'R', 'read'],
        ];
        foreach ($expected as $key => [$group, $level, $batch]) {
            $this->assertSame([$group, $level, $batch], [$this->map()[$key][0], $this->map()[$key][1], $this->map()[$key]['batch']], $key);
        }
    }

    public function test_the_unscoped_quote_gets_stay_unscoped(): void
    {
        foreach (self::UNSCOPED_QUOTE_GETS as $key) {
            $this->assertArrayHasKey($key, $this->map());
            $this->assertArrayNotHasKey('quote_param', $this->map()[$key], $key);
            $this->assertArrayNotHasKey('project_param', $this->map()[$key], $key);
        }
    }

    public function test_no_map_key_names_a_route_that_does_not_exist_for_the_phase3_entries(): void
    {
        foreach (array_keys(self::PHASE3_ENTRIES) as $key) {
            $this->assertArrayHasKey($key, $this->registered(), "$key is not a registered route");
        }
    }
}
