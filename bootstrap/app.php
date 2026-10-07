<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RbacAudit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'checkRole' => CheckRole::class,
        ]);

        $middleware->web(append: [
            // RBAC single insertion point. Runs after auth; audit-mode by default so it
            // never blocks an existing request (see config/rbac.php).
            RbacAudit::class,
        ]);
    })
    ->withProviders([
        App\Providers\EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 403 || ! $request->user()) {
                return null;
            }

            \App\Support\Rbac\DenialRecorder::record($request);

            $message = trim((string) $e->getMessage());
            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = 'You do not have permission to perform this action.';
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['rbac_error' => true, 'message' => $message], 403);
            }

            $previous = url()->previous();
            if ($previous !== $request->fullUrl() && $previous !== url('/') && str_starts_with($previous, $request->getSchemeAndHttpHost())) {
                $redirect = redirect($previous)->with('rbac_denied', $message);

                return $request->isMethod('GET') ? $redirect : $redirect->withInput();
            }

            return response()->view('errors.rbac-403', ['message' => $message], 403);
        });
    })->create();
