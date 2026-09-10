<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFQ workflow tables (doc §6.6, buyer flow confirmed in scope for Release 1).
 *
 * rfq_requests   — the RFQ a buyer org creates and sends to one or more sellers
 * rfq_recipients — one row per seller org the RFQ is sent to
 * rfq_responses  — a seller org's quote response to an RFQ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('deadline')->nullable();

            $table->enum('status', ['draft', 'sent', 'closed', 'converted', 'cancelled'])->default('draft');

            $table->timestamps();

            $table->index(['org_id', 'status']);
        });

        Schema::create('rfq_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rfq_request_id')->constrained('rfq_requests')->cascadeOnDelete();
            $table->foreignId('seller_org_id')->constrained('organizations')->cascadeOnDelete();

            $table->enum('status', ['pending', 'responded', 'declined'])->default('pending');

            $table->timestamps();

            $table->unique(['rfq_request_id', 'seller_org_id']);
            $table->index(['seller_org_id', 'status']);
        });

        Schema::create('rfq_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rfq_request_id')->constrained('rfq_requests')->cascadeOnDelete();
            $table->foreignId('seller_org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->decimal('total_price', 12, 2);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['pending_review', 'selected', 'rejected'])->default('pending_review');

            $table->timestamps();

            $table->index(['rfq_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_responses');
        Schema::dropIfExists('rfq_recipients');
        Schema::dropIfExists('rfq_requests');
    }
};
