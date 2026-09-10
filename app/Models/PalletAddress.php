<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PalletAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'project_name',
        'address1',
        'city',
        'state_id',
        'postcode'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function state()
    {
        return $this->belongsTo(StateTax::class, 'state_id');
    }

    public function pallets()
    {
        return $this->hasMany(Pallet::class, 'pallet_address_id');
    }
}
