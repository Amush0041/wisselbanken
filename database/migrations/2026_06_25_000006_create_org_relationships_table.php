<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC module — captures org-to-org connections (buyer-seller trading partnerships,
 * distributor-manufacturer authorization, GC-subcontractor links, GPO membership,
 * delegation, etc). The "relationship mesh" that makes this an Ariba-style network.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('to_org_id')->constrained('organizations')->cascadeOnDelete();
            // e.g. buyer_seller, distributor_manufacturer, gc_subcontractor, gpo_member
            $table->string('relationship_type');
            $table->string('priority')->nullable();   // Critical / High / Medium
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['from_org_id', 'to_org_id', 'relationship_type'], 'org_rel_from_to_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_relationships');
    }
};
