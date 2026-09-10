<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saved_lists', function (Blueprint $table) {
            // Drop the state column and add state_id
            $table->dropColumn('state');
            $table->unsignedBigInteger('state_id')->nullable()->after('city');
            $table->foreign('state_id')->references('id')->on('state_taxes')->onDelete('set null');
        });

        Schema::table('palletes', function (Blueprint $table) {
            // Drop the state column and add state_id
            $table->dropColumn('state');
            $table->unsignedBigInteger('state_id')->nullable()->after('city');
            $table->foreign('state_id')->references('id')->on('state_taxes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_lists', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
            $table->string('state')->nullable()->after('city');
        });

        Schema::table('palletes', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
            $table->string('state')->nullable()->after('city');
        });
    }
}; 