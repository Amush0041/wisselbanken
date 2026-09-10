<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan crosswalk (plan §4.6): maps a buyer's plan line item code to a WisselBanken SKU
 * and then to the manufacturer part number. Project-scoped buyer data.
 *
 * UI is deferred to a future release; the data model and access controls are
 * established in Release 1 as explicitly required by the plan.
 *
 * Permission enforcement reuses estimate_management scoped via quote_id (project_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_crosswalk', function (Blueprint $table) {
            $table->id();

            // Org + project (quote) scope — both required on every permission check.
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();

            // The three identifiers that form the crosswalk mapping.
            $table->string('plan_line_code');            // buyer's own line item code from their plans
            $table->unsignedBigInteger('product_id')->nullable(); // WisselBanken SKU (products.id)
            $table->string('manufacturer_part_number')->nullable();

            // Optional descriptive fields.
            $table->string('description')->nullable();
            $table->text('notes')->nullable();

            // Audit trail.
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['org_id', 'quote_id']);
            $table->index('plan_line_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_crosswalk');
    }
};
