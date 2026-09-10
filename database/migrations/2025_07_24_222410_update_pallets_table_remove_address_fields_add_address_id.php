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
        Schema::table('palletes', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['state_id']);
            
            // Remove all address-related columns
            $table->dropColumn([
                'first_name', 'last_name', 'email', 'phone', 'company',
                'address1', 'address2', 'city', 'state_id', 'postcode', 'country'
            ]);
            
            // Add pallet_address_id foreign key
            $table->unsignedBigInteger('pallet_address_id')->nullable()->after('saved_list_id');
            $table->foreign('pallet_address_id')->references('id')->on('pallet_addresses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('palletes', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['pallet_address_id']);
            $table->dropColumn('pallet_address_id');
            
            // Add back the address columns
            $table->string('first_name')->nullable()->after('quantity');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('company')->nullable()->after('phone');
            $table->string('address1')->nullable()->after('company');
            $table->string('address2')->nullable()->after('address1');
            $table->string('city')->nullable()->after('address2');
            $table->unsignedBigInteger('state_id')->nullable()->after('city');
            $table->string('postcode')->nullable()->after('state_id');
            $table->string('country')->nullable()->after('postcode');
            
            // Add back the foreign key for state_id
            $table->foreign('state_id')->references('id')->on('state_taxes')->onDelete('set null');
        });
    }
};
