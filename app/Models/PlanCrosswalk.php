<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan crosswalk (doc §4.6) — maps a buyer's plan line item code to a
 * WisselBanken SKU and a manufacturer part number within a project.
 * UI is deferred to a future release; this model satisfies the Release 1
 * data-model and access-control requirement.
 */
class PlanCrosswalk extends Model
{
    protected $table = 'plan_crosswalk';

    protected $fillable = [
        'org_id',
        'project_id',
        'plan_line_code',
        'product_id',
        'manufacturer_part_number',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rbac\Organization::class, 'org_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
