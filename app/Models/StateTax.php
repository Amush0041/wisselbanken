<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateTax extends Model
{
    protected $table = 'state_taxes';
    protected $fillable = [
        'state',
        'state_tax_rate',
        'avg_local_tax_rate',
        'max_local',
        'combined_tax_rate',
    ];
} 