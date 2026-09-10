<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rbac\OrgInvite;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function show(string $token)
    {
        $invite = OrgInvite::with(['organization', 'role'])
            ->where('token', $token)
            ->first();

        if (! $invite || $invite->isUsed() || $invite->isExpired()) {
            return view('auth.invite-invalid');
        }

        return view('auth.invite-register', compact('invite', 'token'));
    }

    public function register(Request $request, string $token)
    {
        $invite = OrgInvite::with(['organization', 'role'])
            ->where('token', $token)
            ->first();

        if (! $invite || $invite->isUsed() || $invite->isExpired()) {
            return redirect()->route('login')->with('error', 'This invite link is invalid or has expired.');
        }

        $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create user — no new org, no org type fields needed
        $user = User::create([
            'name'              => $request->name,
            'email'             => $invite->email,
            'password'          => Hash::make($request->password),
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        // Assign the invited role in the org
        app(RoleAssignmentService::class)->assign(
            $invite->invited_by,
            $user->id,
            $invite->org_id,
            $invite->role_id,
        );

        // Mark invite as used
        $invite->update(['used_at' => now()]);

        // Set active org session and log in
        session([config('rbac.current_org_session_key') => $invite->org_id]);
        Auth::login($user);

        return redirect()->route('user.dashboard')->with('success',
            'Welcome! You have joined ' . $invite->organization->name . ' as ' . $invite->role->name . '.'
        );
    }
}
