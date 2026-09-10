<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sod_conflict_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id_a');
            $table->unsignedBigInteger('role_id_b');
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('role_id_a')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('role_id_b')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique(['role_id_a', 'role_id_b']);
        });
        // Rules are seeded by Database\Seeders\Rbac\SodConflictRuleSeeder (run via RbacSeeder).
    }

    public function down(): void
    {
        Schema::dropIfExists('sod_conflict_rules');
    }
};
