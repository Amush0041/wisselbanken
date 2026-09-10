<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id');
            $table->unsignedBigInteger('target_user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('action');          // 'assigned' | 'removed'
            $table->unsignedBigInteger('performed_by')->nullable(); // null = system/onboarding

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->index(['org_id', 'created_at']);
            $table->index(['org_id', 'target_user_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignment_logs');
    }
};
