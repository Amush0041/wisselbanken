<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC module — seeded once with the roles defined in the client's role matrix.
 * Release 1 = phase P1 + P2 (31 roles); Release 2 = phase P3. All roles are seeded
 * with a phase tag so Release 2 needs only feature work later, not new role rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // e.g. "Estimator", "Executive Approver"
            $table->string('slug')->unique();             // stable identifier, e.g. "estimator"
            $table->string('category');                   // e.g. Platform, Organization, Procurement
            $table->enum('phase', ['P1', 'P2', 'P3'])->default('P1');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
