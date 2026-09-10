<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns ApprovalRoutingService needs on the orders table:
 *  - status: extends the enum to include 'pending_approval' (fixes a runtime bug where
 *    CheckoutController sets this status but the column doesn't accept it)
 *  - org_id:       scopes the order to an organisation so approvers can filter their queue
 *  - approved_by / rejected_by / approval_note: audit trail for the approval action
 */
return new class extends Migration
{
    public function up(): void
    {
        // MariaDB requires a raw ALTER to expand an enum without dropping the column.
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','cancelled','pending_approval') NOT NULL DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('org_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('approved_by')->nullable()->after('notes');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('rejected_by');

            $table->foreign('org_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['org_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['org_id', 'approved_by', 'rejected_by', 'approval_note']);
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
