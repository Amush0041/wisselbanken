<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\OrgRelationship;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Support\Rbac\OrganizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrgSettingsController extends Controller
{
    public function index(): mixed
    {
        $org = $this->currentOrg();

        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        $orgTypes = OrganizationType::all();
        $teamSizes = config('rbac.team_sizes', []);

        return view('user.org-admin.settings', compact('org', 'orgTypes', 'teamSizes'));
    }

    public function update(Request $request): mixed
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'org_type'  => ['required', 'string', 'in:' . implode(',', OrganizationType::slugs())],
            'team_size' => ['required', 'string', 'in:' . implode(',', config('rbac.team_sizes', []))],
        ]);

        $org->update($data);

        return back()->with('success', 'Organization settings saved.');
    }

    public function destroy(Request $request): mixed
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');
        abort_unless($this->isOwner($org), 403, 'Only the organization owner can delete this organization.');

        $name = $org->name;

        DB::transaction(function () use ($org) {
            AuditLog::where('org_id', $org->id)->delete();
            RoleAssignmentLog::where('org_id', $org->id)->delete();
            Delegation::where('org_id', $org->id)->delete();
            OrgRelationship::where('from_org_id', $org->id)->orWhere('to_org_id', $org->id)->delete();
            $org->userOrgRoles()->delete();
            $org->delete();
        });

        session()->forget(config('rbac.current_org_session_key'));

        return redirect()->route('user.dashboard')
            ->with('success', "Organization \"{$name}\" has been permanently deleted.");
    }

    public function transferOwnership(Request $request): mixed
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');
        abort_unless($this->isOwner($org), 403, 'Only the organization owner can transfer ownership.');

        $data = $request->validate([
            'new_owner_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $newOwner = User::where('email', $data['new_owner_email'])->firstOrFail();

        abort_if($newOwner->id === Auth::id(), 422, 'You are already the owner.');

        $ownerRole = Role::where('slug', 'organization_owner')->first();
        abort_if(! $ownerRole, 500, 'Owner role not found.');

        DB::transaction(function () use ($org, $newOwner, $ownerRole) {
            // Remove owner role from current user
            UserOrgRole::where('org_id', $org->id)
                ->where('user_id', Auth::id())
                ->where('role_id', $ownerRole->id)
                ->delete();

            // Grant owner role to new user (upsert to avoid duplicates)
            UserOrgRole::firstOrCreate([
                'org_id'  => $org->id,
                'user_id' => $newOwner->id,
                'role_id' => $ownerRole->id,
            ], ['is_active' => true]);
        });

        return back()->with('success', "Ownership transferred to {$newOwner->name}. You no longer have the Owner role.");
    }

    private function isOwner(Organization $org): bool
    {
        return UserOrgRole::where('org_id', $org->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', 'organization_owner'))
            ->exists();
    }

    private function currentOrg(): ?Organization
    {
        $sessionKey = config('rbac.current_org_session_key');
        $orgId = session($sessionKey);

        $query = UserOrgRole::where('user_id', Auth::id())->where('is_active', true);

        if ($orgId && (clone $query)->where('org_id', $orgId)->exists()) {
            return Organization::find($orgId);
        }

        $firstOrgId = $query->value('org_id');

        return $firstOrgId ? Organization::find($firstOrgId) : null;
    }
}
