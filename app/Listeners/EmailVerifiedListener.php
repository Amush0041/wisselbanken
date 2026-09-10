<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\Pallet;
use App\Models\PalletAddress;

class EmailVerifiedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;
        
        // Check if user has session pallet data
        $sessionPallet = Session::get('pallet', []);
        $sessionAddress = Session::get('pallet_address');
        
        Log::info('Email verified event triggered for user: ' . $user->id, [
            'has_session_pallet' => !empty($sessionPallet),
            'session_pallet_count' => count($sessionPallet),
            'has_session_address' => !empty($sessionAddress),
            'session_keys' => array_keys(Session::all())
        ]);
        
        if (empty($sessionPallet)) {
            Log::info('No session pallet data to migrate for user: ' . $user->id);
            return;
        }
        
        Log::info('Migrating session pallet data for verified user: ' . $user->id, [
            'pallet_items' => count($sessionPallet),
            'has_address' => !empty($sessionAddress)
        ]);
        
        $palletAddressId = null;
        
        // Handle address data if available
        if ($sessionAddress) {
            // Check if user already has a pallet address
            $existingPalletAddress = PalletAddress::where('user_id', $user->id)->first();
            
            if ($existingPalletAddress) {
                // Update existing address
                $existingPalletAddress->update([
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $existingPalletAddress->id;
            } else {
                // Create new pallet address record
                $palletAddress = PalletAddress::create([
                    'user_id' => $user->id,
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $palletAddress->id;
            }
        }
        
        // Migrate each session item to database
        foreach ($sessionPallet as $productColorVariationId => $sessionItem) {
            // Check if item already exists in database
            $existingPallet = Pallet::where('user_id', $user->id)
                ->where('product_variation_color_id', $productColorVariationId)
                ->first();
            
            if ($existingPallet) {
                // Update existing item
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId ?: $existingPallet->pallet_address_id
                ]);
            } else {
                // Create new item
                Pallet::create([
                    'user_id' => $user->id,
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId
                ]);
            }
        }
        
        // Clear session data after successful migration
        Session::forget('pallet');
        Session::forget('pallet_address');
        
        Log::info('Successfully migrated session pallet data for user: ' . $user->id, [
            'migrated_items' => count($sessionPallet),
            'address_id' => $palletAddressId
        ]);
    }
}
