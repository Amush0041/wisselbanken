<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\ApiToken;
use App\Models\Rbac\Organization;
use App\Support\Rbac\CurrentOrg;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\ServiceAccountService;
use App\Support\Rbac\UniversalAdmin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ServiceAccountService $service,
        private readonly PermissionService $permissions,
    )
    {
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

        $tokens = ApiToken::with('user')
            ->when(
                UniversalAdmin::is((int) Auth::id()),
                fn ($q) => $q->where(fn ($w) => $w->where('user_id', Auth::id())->orWhereIn('user_id', $this->orgMemberIds($org))),
                fn ($q) => $q->where('user_id', Auth::id()),
            )
            ->latest()
            ->get();

        $newToken = session()->pull('new_api_token'); // shown once after creation

        return view('user.org-admin.api-tokens', compact('org', 'tokens', 'newToken'));
    }

    public function store(Request $request): mixed
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');
        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'F'),
            403,
            'You do not have permission to perform this action.'
        );

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plaintext = $this->service->createToken(
            Auth::id(),
            $data['name'],
            isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        );

        // Store plaintext in flash once — it is never retrievable again.
        return back()->with('new_api_token', $plaintext)->with('success', 'API token created. Copy it now — it will not be shown again.');
    }

    public function destroy(ApiToken $apiToken): mixed
    {
        $org = $this->currentOrg();
        $ownerOverride = $org && $apiToken->user_id !== Auth::id();
        abort_if(! $org || ($ownerOverride && (! UniversalAdmin::is((int) Auth::id()) || ! in_array((int) $apiToken->user_id, $this->orgMemberIds($org), true))), 403);
        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'F'),
            403,
            'You do not have permission to perform this action.'
        );

        if ($ownerOverride) {
            UniversalAdmin::recordBypass((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'F', null, 'universal_api_token_revoke');
        }

        $this->service->revokeToken($apiToken->id);

        return back()->with('success', 'API token revoked.');
    }

    private function orgMemberIds(Organization $org): array
    {
        return UserOrgRole::where('org_id', $org->id)->where('is_active', true)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    private function currentOrg(): ?Organization
    {
        $id = CurrentOrg::id((int) Auth::id());

        return $id ? Organization::find($id) : null;
    }
}
