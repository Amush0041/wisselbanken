<?php

namespace App\Providers;

use App\Auth\ApiTokenGuard;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\ServiceAccountService;
use App\Support\Rbac\CurrentOrg;
use App\Support\Rbac\UniversalAdmin;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrap();

        // Share org switcher data with the user navbar on every authenticated request.
        View::composer('user.layouts.navbar', function ($view) {
            if (! Auth::check()) {
                return;
            }

            $userId = Auth::id();

            if (UniversalAdmin::is((int) $userId)) {
                $navUserOrgs = Organization::orderBy('name')->get(['id', 'name', 'org_type']);
                $currentId = CurrentOrg::id((int) $userId);
                $navCurrentOrg = $currentId ? $navUserOrgs->firstWhere('id', $currentId) : null;
                $navUniversalAdmin = true;

                $view->with(compact('navUserOrgs', 'navCurrentOrg', 'navUniversalAdmin'));

                return;
            }

            $orgIds = UserOrgRole::where('user_id', $userId)
                ->where('is_active', true)
                ->pluck('org_id')
                ->unique();

            $navUserOrgs = Organization::whereIn('id', $orgIds)
                ->orderBy('name')
                ->get(['id', 'name', 'org_type']);

            $currentOrgId = session(config('rbac.current_org_session_key'));
            $navCurrentOrg = $currentOrgId
                ? $navUserOrgs->firstWhere('id', $currentOrgId)
                : $navUserOrgs->first();

            $view->with(compact('navUserOrgs', 'navCurrentOrg'));
        });

        // @canDo('group', 'level') / @endCanDo — permission-gate any Blade block.
        // Platform admins (role='admin') always pass. Returns false when no org context.
        Blade::if('canDo', function (string $group, string $level): bool {
            if (! auth()->check()) {
                return false;
            }
            if (auth()->user()->role === 'admin') {
                return true;
            }
            $orgId = CurrentOrg::sessionOrg((int) auth()->id());
            if (! $orgId) {
                return false;
            }
            return app(PermissionService::class)
                ->checkPermission(auth()->id(), (int) $orgId, $group, $level);
        });

        // Blade::if('canDo') auto-registers @endcanDo (lowercase 'c') but templates use @endCanDo.
        // Register the capital-C alias so both forms compile to endif.
        Blade::directive('endCanDo', fn () => '<?php endif; ?>');

        // @cannotDo — inverse of @canDo. Useful for showing disabled states.
        Blade::if('cannotDo', function (string $group, string $level): bool {
            if (! auth()->check()) {
                return true;
            }
            if (auth()->user()->role === 'admin') {
                return false;
            }
            $orgId = CurrentOrg::sessionOrg((int) auth()->id());
            if (! $orgId) {
                return true;
            }
            return ! app(PermissionService::class)
                ->checkPermission(auth()->id(), (int) $orgId, $group, $level);
        });

        Blade::directive('endCannotDo', fn () => '<?php endif; ?>');

        // Register the 'api_token' guard driver for service-account / non-human callers.
        Auth::extend('api_token', function (Application $app, string $name, array $config) {
            return new ApiTokenGuard(
                $app->make(ServiceAccountService::class),
                $app->make('request'),
            );
        });
    }
}
