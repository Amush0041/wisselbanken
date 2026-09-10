<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class RefreshCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Regenerate CSRF token if session is getting old
        if (Session::has('_token') && time() - Session::get('_token_time', 0) > 3600) {
            Session::regenerateToken();
            Session::put('_token_time', time());
        }

        // If no CSRF token exists, create one
        if (!Session::has('_token')) {
            Session::regenerateToken();
            Session::put('_token_time', time());
        }

        // Add CSRF token to response headers for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            $response = $next($request);
            $response->headers->set('X-CSRF-TOKEN', csrf_token());
            return $response;
        }

        return $next($request);
    }
}
