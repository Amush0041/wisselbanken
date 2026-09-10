<?php

namespace Database\Seeders\Rbac;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SodConflictRuleSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            ['procurement_manager',    'executive_approver',   'Cannot create and approve purchase orders in the same org.'],
            ['procurement_manager',    'financial_admin',      'Cannot initiate procurement and control the financial ledger.'],
            ['requisitioner',          'executive_approver',   'Cannot submit and approve the same requests.'],
            ['ap_invoice_clerk',       'executive_approver',   'Cannot process invoices and approve the underlying purchases.'],
            ['ap_invoice_clerk',       'financial_admin',      'Cannot perform AP entry and manage all financial records simultaneously.'],
            ['estimator',              'executive_approver',   'Cannot author and approve the same estimates.'],
            ['contract_manager',       'executive_approver',   'Cannot draft and approve contracts in the same organization.'],
            ['budget_owner',           'ap_invoice_clerk',     'Cannot own the budget and also process payments against it.'],
        ];

        foreach ($pairs as [$slugA, $slugB, $reason]) {
            $idA = DB::table('roles')->where('slug', $slugA)->value('id');
            $idB = DB::table('roles')->where('slug', $slugB)->value('id');

            if (! $idA || ! $idB) {
                continue;
            }

            // Check both orderings so we never create a reversed duplicate.
            $exists = DB::table('sod_conflict_rules')
                ->where(fn ($q) => $q->where('role_id_a', $idA)->where('role_id_b', $idB))
                ->orWhere(fn ($q) => $q->where('role_id_a', $idB)->where('role_id_b', $idA))
                ->exists();

            if (! $exists) {
                [$a, $b] = $idA < $idB ? [$idA, $idB] : [$idB, $idA];
                DB::table('sod_conflict_rules')->insert([
                    'role_id_a'  => $a,
                    'role_id_b'  => $b,
                    'reason'     => $reason,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
