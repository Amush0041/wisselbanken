<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC module — seeded once with the 25 permission groups. The slug is the key the
 * permission engine and route map use (e.g. 'procurement', 'approval_authority').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // e.g. "Approval Authority"
            $table->string('slug')->unique();   // e.g. "approval_authority"
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_groups');
    }
};
