<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\Organization;
use App\Support\Rbac\CurrentOrg;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\DelegationService;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\RoleAssignmentService;
use App\Support\Rbac\UniversalAdmin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DelegationController extends Controller
{
    public function __construct(
        private readonly DelegationService $delegations,
        private readonly RoleAssignmentService $assignments,
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(): mixed
    {
        $org = $this->currentOrg();

        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'R'),
            403,
            'You do not have permission to perform this action.'
        );

        // Delegations granted FROM current user (they delegated their identity)
        $granted = Delegation::with(['toUser', 'fromUser', 'grantedBy'])
            ->when(! UniversalAdmin::is((int) Auth::id()), fn ($q) => $q->where('from_user_id', Auth::id()))
            ->where('org_id', $org->id)
            ->latest()
            ->get();

        // Delegations granted TO current user (they act on behalf of someone)
        $received = Delegation::with(['fromUser', 'grantedBy'])
            ->where('to_user_id', Auth::id())
            ->where('org_id', $org->id)
            ->latest()
            ->get();

        // All org members for the delegate picker
        $orgMembers = User::whereIn('id', function ($q) use ($org) {
            $q->select('user_id')->from('user_org_roles')
                ->where('org_id', $org->id)->where('is_active', true);
        })->where('id', '!=', Auth::id())->orderBy('name')->get();

        return view('user.org-admin.delegations', compact('org', 'granted', 'received', 'orgMembers'));
    }

    public function store(Request $request): mixed
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');
        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'O'),
            403,
            'You do not have permission to perform this action.'
        );

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([Auth::id()])],
            'starts_at'  => ['required', 'date', 'before:expires_at'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $this->delegations->grant(
            grantedBy: Auth::id(),
            fromUserId: Auth::id(),
            toUserId: (int) $data['to_user_id'],
            orgId: $org->id,
            startsAt: Carbon::parse($data['starts_at']),
            expiresAt: Carbon::parse($data['expires_at']),
        );

        // Peel-off suggestion (plan §6.3): if the delegator holds roles the delegatee does
        // not yet have permanently, suggest converting the delegation into a formal role
        // assignment so that the owner can remove those roles from their own account.
        $toUserId = (int) $data['to_user_id'];
        $myRoleIds = UserOrgRole::where('user_id', Auth::id())
            ->where('org_id', $org->id)
            ->where('is_active', true)
            ->pluck('role_id');

        $missingRoles = $myRoleIds->filter(function ($roleId) use ($toUserId, $org) {
            return ! $this->assignments->holdsRole($toUserId, $org->id, $roleId);
        });

        if ($missingRoles->isNotEmpty()) {
            $roleNames = Role::whereIn('id', $missingRoles)->pluck('name')->implode(', ');
            return back()
                ->with('success', 'Delegation granted successfully.')
                ->with('peel_off_suggestion', "You hold roles the delegate doesn't: {$roleNames}. If this is a permanent arrangement, consider formally assigning those roles to them and removing them from your account.");
        }

        return back()->with('success', 'Delegation granted successfully.');
    }

    public function destroy(Delegation $delegation): mixed
    {
        $org = $this->currentOrg();
        $ownerOverride = $org && $delegation->from_user_id !== Auth::id();
        abort_if(! $org || ($ownerOverride && (! UniversalAdmin::is((int) Auth::id()) || (int) $delegation->org_id !== (int) $org->id)), 403);
        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'O'),
            403,
            'You do not have permission to perform this action.'
        );

        if ($ownerOverride) {
            UniversalAdmin::recordBypass((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'O', null, 'universal_delegation_revoke');
        }

        $this->delegations->revoke($delegation->id);

        return back()->with('success', 'Delegation revoked.');
    }

    private function currentOrg(): ?Organization
    {
        $id = CurrentOrg::id((int) Auth::id());

        return $id ? Organization::find($id) : null;
    }
}
