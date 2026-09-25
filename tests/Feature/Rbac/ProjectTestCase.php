<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\RbacSetting;
use App\Models\Rbac\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 fixture. Extends the RbacTestCase stubs with only the tables and columns the read paths
 * touch (quotes columns, customers, saved_lists, quote_items, products, rfq_*).
 *
 * Ids are chosen so quotes.id and projects.id collide on purpose:
 *   projects: P1=1 (org A), P2=2 (org A), P3=3 (org B)
 *   quotes:   Q0=1 (NULL project), Q1=2 (P1), Q2=3 (P1), Q3=4 (P2), Q4=5 (P3)
 * so quote 1 is "project 1", and quote 3 (in P1) has the id of P3 (another org's project).
 */
abstract class ProjectTestCase extends RbacTestCase
{
    protected Organization $orgA;
    protected Organization $orgB;

    /** estimator in A, member of P1 */
    protected User $est;
    /** viewer_read_only in A, member of P1 */
    protected User $viewer;
    /** superintendent in A, member of P1 */
    protected User $super;
    /** estimator in A, NOT a member of anything */
    protected User $stranger;
    /** estimator in B, member of P3 */
    protected User $outsider;
    /** estimator in A and B, member of P1 (org A row) */
    protected User $multi;
    /** estimator in A, owns Q0-Q3, member of P1 and P2 */
    protected User $owner;

    protected int $p1;
    protected int $p2;
    protected int $p3;
    protected int $q0;
    protected int $q1;
    protected int $q2;
    protected int $q3;
    protected int $q4;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->stubTables();
        $this->buildFixture();
    }

    private function stubTables(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('currency')->nullable();
            $t->date('estimate_date')->nullable();
            $t->string('shipping_method')->nullable();
            $t->text('notes')->nullable();
            $t->text('terms_and_conditions')->nullable();
            $t->text('staff_notes')->nullable();
            $t->string('pdf_path')->nullable();
            $t->decimal('shipping_cost', 10, 2)->nullable();
            $t->string('order_discount_raw')->nullable();
            $t->text('attachments')->nullable();
            $t->text('customer_address')->nullable();
            $t->text('project_address')->nullable();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('company_name')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('saved_lists', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name')->nullable();
            $t->timestamps();
        });
        Schema::create('quote_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('quote_id');
            $t->string('item_type')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('product_variation_color_id')->nullable();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->decimal('unit_price', 10, 2)->default(0);
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->text('item_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
        Schema::create('rfq_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('title')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('deadline')->nullable();
            $t->timestamps();
        });
        Schema::create('rfq_recipients', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rfq_request_id')->nullable();
            $t->unsignedBigInteger('seller_org_id')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });
    }

    private function buildFixture(): void
    {
        $this->orgA = $this->mkOrg('Org A');
        $this->orgB = $this->mkOrg('Org B');

        $this->est = $this->mkUser($this->orgA, 'estimator');
        $this->viewer = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->super = $this->mkUser($this->orgA, 'superintendent');
        $this->stranger = $this->mkUser($this->orgA, 'estimator');
        $this->outsider = $this->mkUser($this->orgB, 'estimator');
        $this->multi = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($this->multi, $this->orgB, 'estimator');
        $this->owner = $this->mkUser($this->orgA, 'estimator');

        $this->p1 = $this->mkProject($this->orgA, $this->owner, 'P1');
        $this->p2 = $this->mkProject($this->orgA, $this->owner, 'P2');
        $this->p3 = $this->mkProject($this->orgB, $this->outsider, 'P3');

        $this->q0 = $this->mkQuote($this->owner, null, ['name' => 'Q0 null project']);
        $this->q1 = $this->mkQuote($this->owner, $this->p1, ['name' => 'Q1']);
        $this->q2 = $this->mkQuote($this->owner, $this->p1, ['name' => 'Q2']);
        $this->q3 = $this->mkQuote($this->owner, $this->p2, ['name' => 'Q3']);
        $this->q4 = $this->mkQuote($this->outsider, $this->p3, ['name' => 'Q4']);

        foreach ([$this->est, $this->viewer, $this->super, $this->multi, $this->owner] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }
        $this->member($this->p2, $this->owner, $this->orgA);
        $this->member($this->p3, $this->outsider, $this->orgB);
    }

    // ---- builders ---------------------------------------------------------------

    protected function mkOrg(string $name): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => 'subcontractor', 'team_size' => 'solo']);
    }

    protected function mkUser(?Organization $org = null, ?string $roleSlug = null): User
    {
        $u = User::factory()->create(['role' => 'user']);
        if ($org && $roleSlug) {
            $this->assignRole($u, $org, $roleSlug);
        }

        return $u;
    }

    protected function assignRole(User $u, Organization $org, string $slug, bool $active = true): void
    {
        DB::table('user_org_roles')->insert([
            'user_id' => $u->id, 'org_id' => $org->id, 'role_id' => Role::where('slug', $slug)->firstOrFail()->id,
            'assigned_at' => now(), 'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function mkProject(Organization $org, User $creator, string $name, ?string $deletedAt = null): int
    {
        return (int) DB::table('projects')->insertGetId([
            'org_id' => $org->id, 'name' => $name, 'status' => 'active', 'created_by' => $creator->id,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deletedAt,
        ]);
    }

    protected function mkQuote(User $owner, ?int $projectId, array $attrs = []): int
    {
        return (int) DB::table('quotes')->insertGetId($attrs + [
            'user_id' => $owner->id, 'project_id' => $projectId, 'quote_number' => 'QT-'.++$this->seq,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function member(int $projectId, User $u, Organization $org, bool $active = true): int
    {
        return (int) DB::table('project_members')->insertGetId([
            'project_id' => $projectId, 'quote_id' => null, 'user_id' => $u->id, 'org_id' => $org->id,
            'is_active' => $active, 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function legacyMember(int $quoteId, User $u, Organization $org, bool $active = true): int
    {
        return (int) DB::table('project_members')->insertGetId([
            'quote_id' => $quoteId, 'project_id' => null, 'user_id' => $u->id, 'org_id' => $org->id,
            'is_active' => $active, 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function setMode(string $mode): void
    {
        RbacSetting::set('rbac_mode', $mode);
        config(['rbac.enforce_batches' => []]);
    }

    protected function auditRows(): array
    {
        return AuditLog::orderBy('id')->get()->all();
    }
}
