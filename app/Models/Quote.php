<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'saved_list_id',
        'customer_id',
        'name',
        'project_name',
        'project_address',
        'customer_address',
        'quote_number',
        'currency',
        'estimate_date',
        'shipping_method',
        'status',
        'notes',
        'terms_and_conditions',
        'staff_notes',
        'pdf_path',
        'shipping_cost',
        'order_discount_raw',
        'attachments',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'shipping_cost' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function savedList()
    {
        return $this->belongsTo(SavedList::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function calculateTotal()
    {
        return $this->items->sum('subtotal') ?? 0;
    }

    public static function generateQuoteNumber()
    {
        $year = date('Y');
        
        // Get all quote numbers for this year (including soft-deleted) to find the max
        $existingNumbers = self::withTrashed()
            ->whereYear('created_at', $year)
            ->pluck('quote_number')
            ->map(function($number) {
                // Extract numeric part (e.g., "QT-2026-0001" -> 1)
                return (int) substr($number, -4);
            })
            ->toArray();
        
        // Find the maximum number and increment
        $maxNumber = !empty($existingNumbers) ? max($existingNumbers) : 0;
        $number = $maxNumber + 1;
        
        $quoteNumber = 'QT-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
        
        // Double-check uniqueness (handle race conditions)
        $counter = 0;
        while (self::withTrashed()->where('quote_number', $quoteNumber)->exists() && $counter < 100) {
            $number++;
            $quoteNumber = 'QT-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
            $counter++;
        }
        
        return $quoteNumber;
    }
}
