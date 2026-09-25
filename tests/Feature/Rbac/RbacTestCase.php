<?php

namespace Tests\Feature\Rbac;

use Database\Seeders\Rbac\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Isolated base for RBAC tests.
 *
 * The app's full migration set cannot `migrate:fresh` on an empty database (a few early
 * 2025_01_15 migrations alter tables that other migrations don't create until later), so
 * RefreshDatabase is not usable here. Instead each test runs on a fresh in-memory sqlite
 * connection and we build only the schema the RBAC module needs: minimal `users` and
 * `quotes` stand-ins plus the seven real RBAC migrations (run via `require`, which
 * re-evaluates the file each call). This keeps the RBAC schema sourced from the actual
 * migration files while staying fully isolated from the rest of the app.
 */
abstract class RbacTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Scope a fresh in-memory sqlite database to RBAC tests only — without changing
        // the suite-wide default connection (other tests still use the configured DB).
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();
        $this->seed(RbacSeeder::class);
    }

    private function buildSchema(): void
    {
        // Minimal stand-in for the existing users table (RBAC references users.id).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('email')->unique();
            $table->string('role')->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Minimal stand-in for the existing quotes table (a "project" = a Quote/Estimate).
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('saved_list_id')->nullable();
            $table->string('name')->nullable();
            $table->string('project_name')->nullable();
            $table->string('quote_number')->unique();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        // Run the seven real RBAC migrations (require re-evaluates each call → fresh objects).
        $dir = database_path('migrations');
        $migrations = [
            '2026_06_25_000001_create_organizations_table.php',
            '2026_06_25_000002_create_roles_table.php',
            '2026_06_25_000003_create_permission_groups_table.php',
            '2026_06_25_000004_create_role_permissions_table.php',
            '2026_06_25_000005_create_user_org_roles_table.php',
            '2026_06_25_000006_create_org_relationships_table.php',
            '2026_06_25_000007_create_project_members_table.php',
            '2026_06_25_000008_add_team_size_to_organizations_table.php',
            '2026_06_25_000009_create_rbac_audit_logs_table.php',
            '2026_06_25_000010_create_delegations_table.php',
            '2026_06_25_000011_create_api_tokens_table.php',
            '2026_07_07_000001_create_rbac_settings_table.php',
            '2026_07_07_000002_create_sod_conflict_rules_table.php',
            '2026_07_08_000001_create_role_assignment_logs_table.php',
            '2026_09_01_000001_create_plan_crosswalk_table.php',
            '2026_09_24_000001_create_projects_table.php',
            '2026_09_24_000002_add_project_id_to_quotes_table.php',
            '2026_09_24_000003_add_project_id_to_project_members_table.php',
            '2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php',
            '2026_09_24_000005_add_quote_id_to_rbac_audit_logs_table.php',
            '2026_09_24_000006_create_project_member_logs_table.php',
            '2026_09_01_000003_create_rfq_tables.php',
            '2026_09_26_000001_add_project_id_to_rfq_requests_table.php',
        ];

        foreach ($migrations as $file) {
            (require $dir . '/' . $file)->up();
        }
    }
}
