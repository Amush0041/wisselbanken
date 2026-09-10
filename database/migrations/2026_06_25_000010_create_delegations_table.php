<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            // The user whose identity is being shared (the principal).
            $table->foreignId('from_user_id')->constrained('users');
            // The user who will act on behalf of from_user (the delegate).
            $table->foreignId('to_user_id')->constrained('users');
            // Delegation is always scoped to one organization.
            $table->foreignId('org_id')->constrained('organizations');
            // Who granted this delegation.
            $table->foreignId('granted_by')->constrained('users');
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            // Soft-disable without losing audit history.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['to_user_id', 'org_id', 'is_active', 'starts_at', 'expires_at'], 'delegations_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
