<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * RbacController::destroyUser refuses to delete a user who created customers that quotes (soft-deleted included) reference.
 * est is a plain member (no projects, no quotes) of org A and P1; super and owner are teammates.
 */
class UserDeleteCustomersGuardTest extends ProjectTestCase
{
    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformAdmin = User::factory()->create(['role' => 'admin']);
        config(['rbac.universal_admin_user_ids' => []]);
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function del(User $actor, User $target)
    {
        return $this->actingAs($actor)->from('/admin/rbac/users')->delete(route('admin.rbac.users.destroy', $target));
    }

    private function cust(User $creator, string $name = 'C'): int
    {
        return (int) DB::table('customers')->insertGetId([
            'user_id' => $creator->id, 'org_id' => $this->orgA->id, 'company_name' => $name, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function useIn(int $customerId, User $quoteOwner, ?string $deletedAt = null): int
    {
        return $this->mkQuote($quoteOwner, $this->p1, ['customer_id' => $customerId, 'deleted_at' => $deletedAt]);
    }

    private function msg(int $n): string
    {
        return $n === 1
            ? 'This user created 1 customer that is used by estimates and cannot be deleted. Reassign or remove those estimates first.'
            : "This user created {$n} customers that are used by estimates and cannot be deleted. Reassign or remove those estimates first.";
    }

    #[DataProvider('modes')]
    public function test_a_customer_used_by_a_teammates_quote_blocks_deletion(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->est);
        $q = $this->useIn($c, $this->super);
        $logs = DB::table('project_member_logs')->count();

        $this->del($this->platformAdmin, $this->est)->assertRedirect('/admin/rbac/users')
            ->assertSessionHas('error', $this->msg(1))->assertSessionMissing('success');

        $this->assertTrue(User::whereKey($this->est->id)->exists());
        $this->assertSame(1, DB::table('customers')->where('id', $c)->count());
        $this->assertSame($c, (int) DB::table('quotes')->where('id', $q)->value('customer_id'));
        $this->assertSame(1, DB::table('user_org_roles')->where('user_id', $this->est->id)->count());
        $this->assertSame(1, DB::table('project_members')->where('user_id', $this->est->id)->where('is_active', 1)->count());
        $this->assertSame($logs, DB::table('project_member_logs')->count(), 'refusal happens before any side effect');
    }

    public function test_a_customer_used_only_by_a_soft_deleted_quote_still_blocks(): void
    {
        $c = $this->cust($this->est);
        $this->useIn($c, $this->super, now()->toDateTimeString());

        $this->del($this->platformAdmin, $this->est)->assertSessionHas('error', $this->msg(1));

        $this->assertTrue(User::whereKey($this->est->id)->exists());
        $this->assertSame(1, DB::table('customers')->where('id', $c)->count());
    }

    public function test_the_guard_only_counts_customers_the_target_created(): void
    {
        $theirs = $this->cust($this->super, 'not est');
        $this->useIn($theirs, $this->owner);
        $this->cust($this->est, 'unused');

        $this->del($this->platformAdmin, $this->est)->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertSame(1, DB::table('customers')->where('id', $theirs)->count(), 'other creators customers untouched');
    }

    #[DataProvider('modes')]
    public function test_unreferenced_customers_do_not_block_and_membership_logs_are_still_written(string $mode): void
    {
        $this->setMode($mode);
        $this->cust($this->est, 'unused 1');
        $this->cust($this->est, 'unused 2');
        $this->mkQuote($this->super, $this->p1, ['customer_id' => null]);
        $this->assertSame(1, DB::table('project_members')->where('user_id', $this->est->id)->where('is_active', 1)->count());

        $this->del($this->platformAdmin, $this->est)->assertRedirect(route('admin.rbac.users'))->assertSessionHas('success');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertSame(0, DB::table('project_members')->where('user_id', $this->est->id)->count());
        $this->assertSame(1, DB::table('project_member_logs')->where('target_user_id', $this->est->id)->where('performed_by', $this->platformAdmin->id)->where('action', 'removed')->where('project_id', $this->p1)->count());
    }

    public function test_a_user_with_no_customers_is_deleted_as_before(): void
    {
        $this->assertSame(0, DB::table('customers')->where('user_id', $this->est->id)->count());

        $this->del($this->platformAdmin, $this->est)->assertSessionHas('success');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
    }

    public function test_only_a_platform_admin_can_reach_the_route(): void
    {
        $c = $this->cust($this->stranger);
        $this->useIn($c, $this->super);

        foreach ([$this->est, $this->owner, $this->outsider] as $actor) {
            $this->actingAs($actor)->deleteJson(route('admin.rbac.users.destroy', $this->stranger))->assertForbidden();
        }
        $this->assertTrue(User::whereKey($this->stranger->id)->exists());

        $plain = $this->mkUser($this->orgA, 'estimator');
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->actingAs($plain)->deleteJson(route('admin.rbac.users.destroy', $this->viewer))->assertForbidden();
            $this->assertTrue(User::whereKey($this->viewer->id)->exists());
        }
    }

    public function test_several_used_customers_are_counted_and_worded_in_the_plural(): void
    {
        $a = $this->cust($this->est, 'A');
        $b = $this->cust($this->est, 'B');
        $this->cust($this->est, 'unused');
        $this->useIn($a, $this->super);
        $this->useIn($a, $this->super);
        $this->useIn($b, $this->owner, now()->toDateTimeString());

        $this->del($this->platformAdmin, $this->est)->assertSessionHas('error', $this->msg(2));

        $this->assertTrue(User::whereKey($this->est->id)->exists());
        $this->assertSame(3, DB::table('customers')->where('user_id', $this->est->id)->count());
    }

    public function test_the_owns_quotes_refusal_takes_precedence(): void
    {
        $c = $this->cust($this->est);
        $this->useIn($c, $this->est);

        $this->del($this->platformAdmin, $this->est)->assertSessionHas('error', 'This user owns quotes and cannot be deleted. Reassign or delete those quotes first.');

        $this->assertTrue(User::whereKey($this->est->id)->exists());
        $this->assertSame(1, DB::table('customers')->where('id', $c)->count());
    }

    public function test_the_project_creator_refusal_still_comes_before_the_customer_guard(): void
    {
        $c = $this->cust($this->owner);
        $this->useIn($c, $this->super);

        $this->del($this->platformAdmin, $this->owner)->assertSessionHas('error', 'This user created projects and cannot be deleted. Reassign or delete those projects first.');
    }
}
