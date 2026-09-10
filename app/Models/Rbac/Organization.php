<?php

namespace App\Models\Rbac;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'org_type',
        'team_size',
    ];

    public function userOrgRoles(): HasMany
    {
        return $this->hasMany(UserOrgRole::class, 'org_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_org_roles', 'org_id', 'user_id')
            ->wherePivot('is_active', true)
            ->distinct();
    }

    public function relationshipsFrom(): HasMany
    {
        return $this->hasMany(OrgRelationship::class, 'from_org_id');
    }

    public function relationshipsTo(): HasMany
    {
        return $this->hasMany(OrgRelationship::class, 'to_org_id');
    }
}
