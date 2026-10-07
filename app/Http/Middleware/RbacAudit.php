<?php

namespace App\Http\Middleware;

use App\Models\Quote;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\RbacSetting;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single RBAC insertion point (plan §5.2, Stage B). It sits after authentication and
 * before the route handlers, looks the incoming route up in config/route_permission_map.php,
 * and calls the central PermissionService.
 *
 * In 'audit' mode it records every request it WOULD have blocked but lets all of them
 * through unchanged (zero user impact). In 'enforce' mode it returns 403 for failed checks
 * whose batch is enabled. Existing route handlers are never individually modified.
 */
class RbacAudit
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Seed the org session key whenever an authenticated user doesn't have one set.
        // This handles post-login requests where the session is fresh (no org key yet).
        if ($request->user() && $request->hasSession()) {
            $sessionKey = config('rbac.current_org_session_key');
            if (! $request->session()->has($sessionKey)) {
                $firstOrg = DB::table('user_org_roles')
                    ->where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->value('org_id');
                if ($firstOrg !== null) {
                    $request->session()->put($sessionKey, (int) $firstOrg);
                }
            }
        }

        $rule = $this->ruleForRequest($request);

        // Unmapped route, or no authenticated user → nothing to check.
        if ($rule === null || ! $request->user()) {
            return $next($request);
        }

        // Platform admins are governed by checkRole:admin, not org-scoped RBAC.
        if ($request->user()->role === 'admin') {
            return $next($request);
        }

        [$pattern, $group, $level, $batch, $projectParam, $quoteParam] = $rule;

        $userId = $request->user()->id;
        $orgId = $this->currentOrgId($request, $userId);
        [$projectId, $quoteId, $unresolved] = $this->resolveScope($request, $projectParam, $quoteParam);

        // A user with no organization context can satisfy no permission.
        if ($orgId === null) {
            return $this->fail($request, $next, $pattern, $group, $level, $projectId, $quoteId, $batch, 'no_org', null);
        }

        if ($unresolved) {
            return $this->fail($request, $next, $pattern, $group, $level, $projectId, $quoteId, $batch, 'project_unresolved', $orgId);
        }

        if ($this->permissions->checkPermission($userId, $orgId, $group, $level, $projectId)) {
            return $next($request); // allowed — nothing to record
        }

        $reason = ($projectId !== null) ? 'no_grant_or_not_project_member' : 'no_grant';

        return $this->fail($request, $next, $pattern, $group, $level, $projectId, $quoteId, $batch, $reason, $orgId);
    }

    /**
     * Resolve the route's project scope to a projects.id (and, for quote-scoped routes, the
     * quotes.id). A scoped route whose project cannot be resolved is unresolved and fails closed.
     *
     * @return array{0:?int,1:?int,2:bool} [projectId, quoteId, unresolved]
     */
    private function resolveScope(Request $request, ?string $projectParam, ?string $quoteParam): array
    {
        if ($projectParam !== null) {
            $projectId = $this->positiveId($request->route($projectParam));

            return [$projectId, null, $projectId === null];
        }

        if ($quoteParam === null) {
            return [null, null, false];
        }

        $value = $request->route($quoteParam);

        if ($value instanceof Model) {
            $projectId = $value->getAttribute('project_id');

            return [$projectId !== null ? (int) $projectId : null, (int) $value->getKey(), $projectId === null];
        }

        $quoteId = $this->positiveId($value);
        if ($quoteId === null) {
            return [null, null, true];
        }

        $projectId = Quote::withTrashed()->whereKey($quoteId)->value('project_id');

        return [$projectId !== null ? (int) $projectId : null, $quoteId, $projectId === null];
    }

    private function positiveId(mixed $value): ?int
    {
        if ($value instanceof Model) {
            $value = $value->getKey();
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Record the failed check, then either pass through (audit) or block (enforce).
     */
    private function fail(
        Request $request,
        Closure $next,
        string $pattern,
        string $group,
        string $level,
        ?int $projectId,
        ?int $quoteId,
        ?string $batch,
        string $reason,
        ?int $orgId,
    ): Response {
        $enforcing = $this->isEnforcing($batch, $orgId);

        $row = AuditLog::create([
            'user_id' => $request->user()->id,
            'org_id' => $orgId,
            'method' => $request->method(),
            'route_uri' => $request->route()?->uri() ?? $request->path(),
            'matched_pattern' => $pattern,
            'permission_group' => $group,
            'required_level' => $level,
            'project_id' => $projectId,
            'quote_id' => $quoteId,
            'batch' => $batch,
            'outcome' => $enforcing ? 'blocked' : 'would_block',
            'reason' => $reason,
        ]);

        if ($enforcing) {
            $message = $this->friendlyMessage($group, $level, $reason, $request->user()->id, $orgId, $projectId);

            // AJAX / JSON requests → JSON payload so the client can show a popup.
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'rbac_error' => true,
                    'message'    => $message,
                    'group'      => $group,
                    'level'      => $level,
                ], 403);
            }

            // Form POSTs and regular GET navigations → redirect back with a flash.
            $redirect = redirect()->back()->with('rbac_denied', $message);
            if ($request->method() !== 'GET') {
                $redirect = $redirect->withInput();
            }
            return $redirect;
        }

        $request->attributes->set('rbac_audit_row', $row->id);

        return $next($request); // audit mode — let it through exactly as before
    }

    /**
     * Look up the matching map rule for this request, trying the exact method first then
     * the '*' wildcard. Returns [pattern, group, level, batch, projectParam, quoteParam] or null.
     *
     * @return array{0:string,1:string,2:string,3:?string,4:?string,5:?string}|null
     */
    private function ruleForRequest(Request $request): ?array
    {
        $route = $request->route();
        if ($route === null) {
            return null;
        }

        $uri = $route->uri();
        $map = config('route_permission_map', []);

        foreach ([$request->method() . ' ' . $uri, '* ' . $uri] as $key) {
            if (! isset($map[$key])) {
                continue;
            }

            $value = $map[$key];

            return [
                $key,
                $value[0],
                $value[1],
                $value['batch'] ?? null,
                $value['project_param'] ?? null,
                $value['quote_param'] ?? null,
            ];
        }

        return null;
    }

    /**
     * The org the user is currently acting in: the session selection if present and still
     * valid, otherwise the user's first active org.
     */
    private function currentOrgId(Request $request, int $userId): ?int
    {
        return CurrentOrg::id($userId);
    }

    /**
     * Convert a technical group + level pair into a human-readable denial message. A route
     * that is project-scoped can fail for two different reasons that look identical to the
     * user unless we tell them apart: the org-level role itself doesn't grant the required
     * level, or the role would grant it but the user just isn't a member of this project
     * (client audit finding "non-member modal text", 2026-09-26). Only the second case gets
     * the membership-specific message.
     */
    private function friendlyMessage(
        string $group,
        string $level,
        string $reason,
        ?int $userId,
        ?int $orgId,
        ?int $projectId,
    ): string {
        if ($reason === 'no_org') {
            return 'You need an active organization to do this. Select or join an organization and try again.';
        }

        if ($reason === 'project_unresolved') {
            return "This project couldn't be found, or it's no longer available.";
        }

        if ($reason === 'no_grant_or_not_project_member'
            && $projectId !== null
            && $userId !== null
            && $orgId !== null
            && $this->permissions->checkPermission($userId, $orgId, $group, $level)) {
            return 'You are not a member of this project. Ask an organization owner or project manager to add you before you can continue.';
        }

        return $this->genericMessage($group, $level);
    }

    private function genericMessage(string $group, string $level): string
    {
        $groupLabels = [
            'estimate_management'          => 'Estimates',
            'user_management'              => 'Team & User Management',
            'product_management'           => 'Products & Services',
            'procurement'                  => 'Orders & Procurement',
            'project_management'           => 'Projects & Lists',
            'organization_management'      => 'Organization Settings',
            'delegation_and_impersonation' => 'Delegations',
            'approval_authority'           => 'Approvals',
            'financial_access'             => 'Financial Data',
            'reporting_and_analytics'      => 'Reports & Analytics',
            'quote_rfq_management'         => 'Quotes & RFQs',
            'order_fulfillment'            => 'Order Fulfillment',
        ];

        $actionLabels = [
            'R' => 'view',
            'S' => 'create',
            'O' => 'edit',
            'A' => 'approve',
            'F' => 'manage',
        ];

        $groupLabel  = $groupLabels[$group]  ?? ucwords(str_replace('_', ' ', $group));
        $actionLabel = $actionLabels[$level] ?? $level;

        return "You don't have permission to {$actionLabel} {$groupLabel}. "
             . 'Contact your organization administrator if you need access.';
    }

    /**
     * Whether this batch should actually be blocked, given the configured mode/batches or the org's per-org enforcement.
     */
    private function isEnforcing(?string $batch, ?int $orgId): bool
    {
        if (RbacSetting::get('rbac_mode', 'audit') === 'enforce') {
            $batches = config('rbac.enforce_batches', []);

            // Empty batch list under 'enforce' = enforce everything.
            if ($batches === [] || ($batch !== null && in_array($batch, $batches, true))) {
                return true;
            }
        }

        // Per-org enforcement covers all batches and can only add to the global rule.
        return $orgId !== null && in_array($orgId, RbacSetting::enforcedOrgIds(), true);
    }
}
