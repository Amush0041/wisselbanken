<?php

namespace App\Http\Middleware;

use App\Support\Rbac\UniversalAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Please login to continue.',
                    'error' => 'unauthorized'
                ], 401);
            }
            return redirect('login');
        }

        if (Auth::user()->role !== $role && ! ($role === 'admin' && UniversalAdmin::is(Auth::user()))) {
            if ($request->expectsJson() || $request->ajax()) {
                $message = $role === 'user' 
                    ? 'Please login as a user to add products to your list. Admin accounts cannot create lists.'
                    : 'You do not have permission to access this resource.';
                
                return response()->json([
                    'message' => $message,
                    'error' => 'forbidden',
                    'role' => Auth::user()->role,
                    'required_role' => $role
                ], 403);
            }
            return redirect('login');
        }

        return $next($request);
    }
}
