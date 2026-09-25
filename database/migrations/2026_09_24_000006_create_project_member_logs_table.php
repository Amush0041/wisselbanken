<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_member_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('target_user_id')->index();
            $table->string('action', 20); // 'added' | 'removed' | 'reactivated'
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_member_logs');
    }
};
