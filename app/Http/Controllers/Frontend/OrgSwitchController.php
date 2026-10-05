<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Support\Rbac\UniversalAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrgSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $request->validate(['org_id' => ['required', 'integer']]);

        $orgId = (int) $request->input('org_id');

        $belongs = UserOrgRole::where('user_id', auth()->id())
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->exists();

        if (! $belongs && UniversalAdmin::is((int) auth()->id())
            && Organization::whereKey($orgId)->exists()) {
            UniversalAdmin::recordBypass((int) auth()->id(), $orgId, null, null, null, 'universal_org_switch');
            $belongs = true;
        }

        if (! $belongs) {
            return back()->with('error', 'You do not have access to that organization.');
        }

        session([config('rbac.current_org_session_key') => $orgId]);

        return redirect()->route('org-admin.overview')
            ->with('success', 'Organization context switched.');
    }
}
