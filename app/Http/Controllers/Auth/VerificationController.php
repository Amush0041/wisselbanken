<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = '/product-filter';

    public function __construct()
    {
        // 'verify' does NOT require auth — the signed URL carries the user ID.
        // Removing auth from verify lets the link work from any browser/device
        // without needing to be logged in first.
        $this->middleware('auth')->except('verify');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * Mark the user's email as verified.
     * Works whether the user is logged in or not — looks them up by URL id.
     */
    public function verify(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            throw new AuthorizationException;
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if (auth()->check() && auth()->id() === $user->id) {
            return redirect($this->redirectPath())->with('verified', true);
        }

        return redirect()->route('login')->with('status', 'Email verified! Please log in to continue.');
    }
}
