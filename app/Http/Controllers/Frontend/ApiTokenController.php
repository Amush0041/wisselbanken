<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\ApiToken;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\ServiceAccountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function __construct(private readonly ServiceAccountService $service)
    {
    }

    public function index(): mixed
    {
        $org = $this->currentOrg();

        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

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
        abort_if($apiToken->user_id !== Auth::id(), 403);

        $this->service->revokeToken($apiToken->id);

        return back()->with('success', 'API token revoked.');
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
