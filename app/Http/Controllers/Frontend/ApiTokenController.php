<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\ApiToken;
use App\Models\Rbac\Organization;
use App\Support\Rbac\CurrentOrg;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\ServiceAccountService;
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

        $tokens = ApiToken::where('user_id', Auth::id())
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
        abort_if(! $org || $apiToken->user_id !== Auth::id(), 403);
        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), (int) $org->id, 'delegation_and_impersonation', 'F'),
            403,
            'You do not have permission to perform this action.'
        );

        $this->service->revokeToken($apiToken->id);

        return back()->with('success', 'API token revoked.');
    }

    private function currentOrg(): ?Organization
    {
        $id = CurrentOrg::id((int) Auth::id());

        return $id ? Organization::find($id) : null;
    }
}
