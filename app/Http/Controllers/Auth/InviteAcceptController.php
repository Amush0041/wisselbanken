<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Rbac\SodConflictException;
use App\Http\Controllers\Controller;
use App\Models\Rbac\OrgInvite;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\RoleAssignmentService;
use App\Support\Rbac\UniversalAdmin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accepting an organization invite with an account that already exists. The invitee must be signed in
 * with a verified e-mail equal to the invited address; the role is assigned only here, never by the inviter.
 */
class InviteAcceptController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentService $assignments,
        private readonly PermissionService $permissions,
    ) {
    }

    public function show(string $token)
    {
        $invite = $this->invitationFor($token);

        return view('auth.invite-accept', compact('invite', 'token'));
    }

    public function accept(string $token)
    {
        $invite = $this->invitationFor($token);

        try {
            DB::transaction(function () use ($invite) {
                $claimed = OrgInvite::whereKey($invite->id)->whereNull('used_at')->update(['used_at' => now()]);
                abort_if($claimed !== 1, 404);

                $this->assignments->assign((int) $invite->invited_by, (int) Auth::id(), (int) $invite->org_id, (int) $invite->role_id);
            });
        } catch (SodConflictException $e) {
            return redirect()->route('invite.review', $token)->with(
                'error',
                'This role cannot be added to your account: you already hold ' . implode(', ', $e->conflictingRoles) . ', which cannot coexist with it in the same organization.'
            );
        }

        session([config('rbac.current_org_session_key') => $invite->org_id]);

        return redirect()->route('user.dashboard')->with('success',
            'Welcome! You have joined ' . $invite->organization->name . ' as ' . $invite->role->name . '.'
        );
    }

    private function invitationFor(string $token): OrgInvite
    {
        $user = Auth::user();
        $invite = OrgInvite::with(['organization', 'role', 'invitedBy'])->where('token', $token)->first();

        abort_unless(
            $invite && $user->hasVerifiedEmail() && strcasecmp(trim($user->email), trim($invite->email)) === 0,
            404
        );

        if ($invite->isUsed() || $invite->isExpired()
            || ! $this->assignments->isAssignableByOrg($invite->role, $invite->organization->org_type)
            || ! $this->inviterStillAuthorised($invite)) {
            throw new HttpResponseException(response()->view('auth.invite-invalid', [], 404));
        }

        return $invite;
    }

    private function inviterStillAuthorised(OrgInvite $invite): bool
    {
        $inviterId = (int) $invite->invited_by;
        $orgId = (int) $invite->org_id;

        // Listed before checkPermission on purpose: for a listed user it would write a bypass row under the inviter's id during the invitee's request.
        if (UniversalAdmin::is($inviterId)) {
            return true;
        }

        return $this->assignments->isActiveMember($inviterId, $orgId)
            && $this->permissions->checkPermission($inviterId, $orgId, 'user_management', 'F');
    }
}
