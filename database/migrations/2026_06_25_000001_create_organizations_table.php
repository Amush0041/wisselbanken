<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC module — represents a buyer, seller, manufacturer, or other organization
 * registered on the platform. Roles are NEVER a column on the user; org membership
 * and role assignment live in user_org_roles instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // One of the 20 supported organization types (stored as a string slug).
            $table->string('org_type')->nullable();
            $table->timestamps();

            $table->index('org_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
