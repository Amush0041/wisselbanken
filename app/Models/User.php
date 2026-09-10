<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Guard against duplicate verification emails within the same request.
     * The Registered event can fire multiple listeners; only the first wins.
     */
    public function sendEmailVerificationNotification(): void
    {
        $key = '_verify_email_sent_' . $this->id;
        if (app()->bound($key)) {
            return;
        }
        app()->instance($key, true);
        parent::sendEmailVerificationNotification();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'company_name',
        'company_logo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function savedLists()
    {
        return $this->hasMany(SavedList::class);
    }

    /*
    |--------------------------------------------------------------------------
    | RBAC module relations (additive — do not alter existing behavior)
    |--------------------------------------------------------------------------
    | Org membership and role assignment live in user_org_roles, never on this
    | model's columns. The legacy `role` column is left untouched.
    */

    public function userOrgRoles()
    {
        return $this->hasMany(\App\Models\Rbac\UserOrgRole::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(
            \App\Models\Rbac\Organization::class,
            'user_org_roles',
            'user_id',
            'org_id'
        )->wherePivot('is_active', true)->distinct();
    }

    public function projectMemberships()
    {
        return $this->hasMany(\App\Models\Rbac\ProjectMember::class);
    }
}
