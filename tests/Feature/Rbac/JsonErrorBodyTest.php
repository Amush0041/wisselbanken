<?php

namespace Tests\Feature\Rbac;

use App\Exceptions\Handler;
use App\Models\Rbac\AuditLog;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, S13: JSON error bodies never carry debug data (exception class, file, line, trace) or model names,
 * whatever APP_DEBUG says. The 403 `rbac_error` shape and 422 validation bodies keep working.
 */
class JsonErrorBodyTest extends ProjectTestCase
{
    private const FORBIDDEN = ['exception', 'trace', '"file"', '"line"', 'App\\\\', 'Illuminate', 'Customer', 'query results', 'JsonErrorBodyTest', 'secret-detail', '.php'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');

        Route::get('_test/boom', fn () => throw new \RuntimeException('secret-detail in /var/www/app/Secret.php'));
        Route::get('_test/conflict', fn () => abort(409, 'Order already exists.'));
        Route::get('_test/server-abort', fn () => abort(503, 'secret-detail maintenance window'));
    }

    public static function debugModes(): array
    {
        return ['debug on' => [true], 'debug off' => [false]];
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function customerOfOrgB(): int
    {
        return (int) DB::table('customers')->insertGetId([
            'user_id' => $this->outsider->id, 'org_id' => $this->orgB->id, 'company_name' => 'OrgBCo',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function inOrgA()
    {
        return $this->actingAs($this->est)->withSession([config('rbac.current_org_session_key') => $this->orgA->id]);
    }

    private function assertNoDebugData($response): void
    {
        $body = (string) $response->getContent();
        foreach (self::FORBIDDEN as $needle) {
            $this->assertStringNotContainsString($needle, $body, "body leaks '$needle'");
        }
        $this->assertSame(['message'], array_keys($response->json()));
    }

    public function test_the_app_handler_replaces_the_framework_one(): void
    {
        $this->assertInstanceOf(Handler::class, app(ExceptionHandler::class));
    }

    #[DataProvider('debugModes')]
    public function test_a_json_404_for_another_orgs_customer_carries_only_a_generic_message(bool $debug): void
    {
        config(['app.debug' => $debug]);
        $id = $this->customerOfOrgB();

        $response = $this->inOrgA()->getJson("customers/$id")->assertNotFound();

        $this->assertSame(['message' => 'Not found.'], $response->json());
        $this->assertNoDebugData($response);
    }

    #[DataProvider('debugModes')]
    public function test_a_json_404_for_a_missing_record_and_for_an_unknown_url_is_the_same_body(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $missing = $this->inOrgA()->getJson('customers/987654')->assertNotFound();
        $unknown = $this->inOrgA()->getJson('no/such/page')->assertNotFound();

        $this->assertSame(['message' => 'Not found.'], $missing->json());
        $this->assertSame(['message' => 'Not found.'], $unknown->json());
        $this->assertNoDebugData($unknown);
    }

    #[DataProvider('debugModes')]
    public function test_a_json_500_from_a_throwing_route_carries_only_a_generic_message(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $response = $this->inOrgA()->getJson('_test/boom')->assertStatus(500);

        $this->assertSame(['message' => 'Server error.'], $response->json());
        $this->assertNoDebugData($response);
    }

    #[DataProvider('debugModes')]
    public function test_a_json_5xx_abort_does_not_echo_its_message(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $response = $this->inOrgA()->getJson('_test/server-abort')->assertStatus(503);

        $this->assertSame(['message' => 'Server error.'], $response->json());
    }

    #[DataProvider('debugModes')]
    public function test_other_4xx_responses_keep_their_message(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $response = $this->inOrgA()->getJson('_test/conflict')->assertStatus(409);

        $this->assertSame(['message' => 'Order already exists.'], $response->json());
    }

    #[DataProvider('modes')]
    public function test_the_403_rbac_error_shape_is_unchanged_with_debug_on(string $mode): void
    {
        config(['app.debug' => true]);
        $this->setMode($mode);

        $response = $this->actingAs($this->viewer)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->deleteJson('customers/1')
            ->assertForbidden();

        $this->assertSame(
            $mode === 'enforce' ? ['rbac_error', 'message', 'group', 'level'] : ['rbac_error', 'message'],
            array_keys($response->json())
        );
        $this->assertTrue($response->json('rbac_error'));
        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertStringNotContainsString('trace', (string) $response->getContent());
        $this->assertStringNotContainsString('exception', (string) $response->getContent());
        $this->assertSame(1, AuditLog::where('user_id', $this->viewer->id)->where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_422_validation_body_is_unchanged_with_debug_on(string $mode): void
    {
        config(['app.debug' => true]);
        $this->setMode($mode);
        $owner = $this->mkUser($this->orgA, 'organization_owner');

        $response = $this->actingAs($owner)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->postJson('org-admin/roles', [])
            ->assertStatus(422);

        $this->assertSame(['message', 'errors'], array_keys($response->json()));
        $this->assertArrayHasKey('user_id', $response->json('errors'));
        $this->assertArrayHasKey('role_id', $response->json('errors'));
    }

    #[DataProvider('debugModes')]
    public function test_a_guest_json_request_still_gets_the_401_message(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $this->getJson('customers/list')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
