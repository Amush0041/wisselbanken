<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedList extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'user_id',
        'name',
        'project_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'address1',
        'address2',
        'city',
        'state_id',
        'postcode',
        'country'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SavedListItem::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function state()
    {
        return $this->belongsTo(StateTax::class, 'state_id');
    }
}
