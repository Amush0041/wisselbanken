<?php

namespace Tests\Feature\Rbac;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerOrgMigrationTest extends RbacTestCase
{
    private function migration(): object
    {
        return require database_path('migrations/2026_10_14_000001_add_org_id_to_customers_table.php');
    }

    private function legacyCustomers(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('company_name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function test_up_adds_a_nullable_org_id_with_an_index_and_leaves_data_alone(): void
    {
        $this->legacyCustomers();
        DB::table('customers')->insert(['user_id' => 1, 'company_name' => 'Old', 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('customers', 'org_id'));
        $col = collect(Schema::getColumns('customers'))->firstWhere('name', 'org_id');
        $this->assertTrue($col['nullable']);
        $this->assertNull(DB::table('customers')->value('org_id'), 'no data change');
        $this->assertContains('customers_org_id_is_active_index', array_column(Schema::getIndexes('customers'), 'name'));
        $idx = collect(Schema::getIndexes('customers'))->firstWhere('name', 'customers_org_id_is_active_index');
        $this->assertSame(['org_id', 'is_active'], $idx['columns']);
    }

    public function test_up_adds_a_foreign_key_to_organizations_that_nulls_on_org_delete(): void
    {
        $this->legacyCustomers();
        $this->migration()->up();

        $fk = collect(Schema::getForeignKeys('customers'))->firstWhere('columns', ['org_id']);
        $this->assertNotNull($fk);
        $this->assertSame('organizations', $fk['foreign_table']);
        $this->assertSame(['id'], $fk['foreign_columns']);
        $this->assertSame('set null', strtolower($fk['on_delete']));

        DB::statement('PRAGMA foreign_keys = ON');
        $org = DB::table('organizations')->insertGetId(['name' => 'Gone', 'org_type' => 'subcontractor', 'team_size' => 'solo', 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('customers')->insertGetId(['user_id' => 1, 'org_id' => $org, 'company_name' => 'X', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('organizations')->where('id', $org)->delete();

        $this->assertNull(DB::table('customers')->where('id', $id)->value('org_id'));
        $this->assertSame(1, DB::table('customers')->where('id', $id)->count(), 'the customer survives its org');
    }

    public function test_a_foreign_org_id_that_does_not_exist_is_rejected(): void
    {
        $this->legacyCustomers();
        $this->migration()->up();
        DB::statement('PRAGMA foreign_keys = ON');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('customers')->insert(['user_id' => 1, 'org_id' => 999, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_second_run_is_harmless(): void
    {
        $this->legacyCustomers();
        $m = $this->migration();
        $m->up();
        $cols = Schema::getColumnListing('customers');
        $idx = Schema::getIndexes('customers');

        $m->up();

        $this->assertSame($cols, Schema::getColumnListing('customers'));
        $this->assertSame($idx, Schema::getIndexes('customers'));
    }

    public function test_down_is_a_no_op_when_the_column_is_absent(): void
    {
        $this->legacyCustomers();

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('customers', 'org_id'));
    }
}
