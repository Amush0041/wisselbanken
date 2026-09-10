<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
class ProfileController extends Controller
{
    public function edit()
    {
        return view('user.profile');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // Validation
        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Update basic fields
        $user->name = $request->name;
        $user->company_name = $request->input('company_name', '');
        // $user->email = $request->email;

        // Update password only if filled
        // if ($request->filled('password')) {
        //     $user->password = Hash::make($request->password);
        // }

        // Handle profile image
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $path = $request->file('profile_image')->store('profiles', 'public');
            $user->profile_image = $path;
        }

        $user->update();

        return redirect()->back()->with('success', 'Profile updated successfully!');
    }
}
