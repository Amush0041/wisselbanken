<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\OrgOnboardingService;
use App\Support\Rbac\OrganizationType;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/product-filter';

    /**
     * Get the post-registration redirect path (fallback — overridden by registered()).
     */
    protected function redirectTo()
    {
        return route('register.complete');
    }

    /**
     * After registration, go straight to the email verification notice.
     */
    protected function registered(Request $request, $user)
    {
        return redirect()->route('verification.notice');
    }

    /**
     * Post-registration confirmation page — shows the auto-assigned roles.
     */
    public function complete()
    {
        $orgId = session(config('rbac.current_org_session_key'));
        $org   = $orgId ? Organization::find($orgId) : null;

        $roles = [];
        if ($org) {
            $roles = UserOrgRole::with('role')
                ->where('user_id', auth()->id())
                ->where('org_id', $org->id)
                ->where('is_active', true)
                ->get()
                ->pluck('role')
                ->filter();
        }

        return view('auth.register-complete', compact('org', 'roles'));
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('complete');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // RBAC onboarding (plan §6.1): org type + team size drive the role bundle.
            'company_name' => ['nullable', 'string', 'max:255'],
            'org_type' => ['required', Rule::in(OrganizationType::slugs())],
            'team_size' => ['required', Rule::in(config('rbac.team_sizes'))],
        ]);
    }

    /**
     * Create a new user instance after a valid registration, then onboard their
     * organization and auto-assign the starting role bundle.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $orgName = $data['company_name'] ?? ($data['name'] . "'s Organization");

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company_name' => $data['company_name'] ?? null,
            // Legacy role column kept intact so existing flows are unaffected.
            'role' => 'user',
            'password' => Hash::make($data['password']),
        ]);

        $org = app(OrgOnboardingService::class)->onboard(
            $user->id,
            $orgName,
            $data['org_type'],
            $data['team_size'],
        );

        // Start the user acting in their new organization.
        session([config('rbac.current_org_session_key') => $org->id]);

        return $user;
    }
}
