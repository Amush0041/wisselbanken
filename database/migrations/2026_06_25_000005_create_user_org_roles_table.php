<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC module — THE KEY TABLE. Every role assignment in the system is a row here,
 * linking a user, an organization, and a role. This is what allows one user to hold
 * multiple roles, and to belong to multiple orgs with a different role in each.
 * Every permission check is scoped to org_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_org_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            // Audit trail of who granted the role (nullable for system/seed assignment).
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            // Soft-removal of a role without deleting history.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'org_id', 'is_active']);
            $table->unique(['user_id', 'org_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_org_roles');
    }
};
