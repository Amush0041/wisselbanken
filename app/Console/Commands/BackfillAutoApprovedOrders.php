<?php

namespace App\Console\Commands;

use App\Models\Rbac\AuditLog;
use App\Services\Rbac\ApprovalRoutingService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackfillAutoApprovedOrders extends Command
{
    protected $signature = 'orders:backfill-auto-approval
        {--dry-run}
        {--force : required when APP_ENV=production}';

    protected $description = 'Approve orders that the sole approver of an organization placed and that were left in status pending';

    private const CHUNK = 400;

    public function handle(): int
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'approved_by')
            || ! Schema::hasColumn('orders', 'rejected_by') || ! Schema::hasColumn('orders', 'approval_note')) {
            $this->error('the orders approval columns are missing; nothing to do');

            return 2;
        }

        $dry = (bool) $this->option('dry-run');

        if (! $dry && app()->environment('production') && ! $this->option('force')) {
            $this->error('production run requires --force');

            return 2;
        }

        $dir = storage_path('app/backfill/orders-auto-approval/'.now()->format('Ymd_His'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('cannot create report directory');

            return 2;
        }

        $report = fopen($dir.'/orders.csv', 'w');
        fputcsv($report, ['order_id', 'order_number', 'org_id', 'user_id', 'result']);

        $counts = ['selected' => 0, 'approved' => 0, 'skipped' => 0, 'failed' => 0];
        $listed = [];

        $this->selection()->chunkById(self::CHUNK, function ($orders) use (&$counts, &$listed, $dry, $report) {
            foreach ($orders as $order) {
                $counts['selected']++;
                $listed[] = [$order->id, $order->order_number, $order->org_id, $order->user_id];

                if ($dry) {
                    fputcsv($report, [$order->id, $order->order_number, $order->org_id, $order->user_id, 'would_approve']);

                    continue;
                }

                $result = $this->approve($order);
                $counts[$result]++;
                fputcsv($report, [$order->id, $order->order_number, $order->org_id, $order->user_id, $result]);
            }
        });

        fclose($report);

        if ($listed !== []) {
            $this->table(['id', 'order_number', 'org', 'submitter'], $listed);
        }

        $summary = ($dry ? 'DRY RUN: ' : '').sprintf(
            'selected %d, approved %d, skipped %d, failed %d',
            $counts['selected'], $counts['approved'], $counts['skipped'], $counts['failed']
        );
        file_put_contents($dir.'/summary.txt', $summary.PHP_EOL);
        $this->info($summary);
        $this->line('reports: '.$dir);

        if ($counts['selected'] === 0) {
            $this->info('nothing to do');
        }

        return $counts['failed'] > 0 ? 1 : 0;
    }

    private function selection(): Builder
    {
        return DB::table('orders')
            ->where('status', 'pending')
            ->whereNotNull('org_id')
            ->whereNull('approved_by')
            ->whereNull('rejected_by')
            ->whereNotNull('user_id');
    }

    private function approve(object $order): string
    {
        try {
            return DB::transaction(function () use ($order) {
                $changed = DB::table('orders')
                    ->where('id', $order->id)
                    ->where('status', 'pending')
                    ->whereNull('approved_by')
                    ->whereNull('rejected_by')
                    ->update([
                        'status' => 'processing',
                        'approved_by' => $order->user_id,
                        'approval_note' => ApprovalRoutingService::AUTO_APPROVAL_NOTE,
                        'updated_at' => now(),
                    ]);

                if ($changed !== 1) {
                    return 'skipped';
                }

                AuditLog::event(
                    'auto_approved',
                    (int) $order->user_id,
                    (int) $order->org_id,
                    'approval_authority',
                    'A',
                    'sole_approver_backfill order '.$order->order_number,
                    'CLI',
                    'artisan orders:backfill-auto-approval',
                );

                return 'approved';
            });
        } catch (\Throwable $e) {
            Log::error('orders:backfill-auto-approval failed for an order', ['order_id' => $order->id, 'error' => get_class($e)]);
            $this->error('order '.$order->id.' failed: '.get_class($e));

            return 'failed';
        }
    }
}
