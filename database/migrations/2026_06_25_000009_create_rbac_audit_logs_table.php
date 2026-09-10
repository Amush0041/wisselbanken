<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC Phase 2 — audit-mode log. While the middleware runs in audit mode it records every
 * request it WOULD have blocked, without blocking it. Reviewing these rows produces a
 * complete picture of permission mismatches before enforcement is switched on (plan §5.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->string('method', 10);
            $table->string('route_uri');
            $table->string('matched_pattern')->nullable();
            $table->string('permission_group')->nullable();
            $table->string('required_level', 1)->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('batch')->nullable();
            // 'would_block' (audit) or 'blocked' (enforce). Only failed checks are recorded.
            $table->string('outcome');
            $table->string('reason')->nullable();   // e.g. no_org, no_grant, not_project_member
            $table->timestamps();

            $table->index(['outcome', 'created_at']);
            $table->index(['user_id', 'org_id']);
            $table->index('permission_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_audit_logs');
    }
};
