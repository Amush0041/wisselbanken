<?php

namespace App\Support\Rbac;

use App\Models\Rbac\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DenialRecorder
{
    public static function record(Request $request): void
    {
        try {
            if ($request->attributes->get('rbac_denial_recorded') || ! $request->user()) {
                return;
            }

            $request->attributes->set('rbac_denial_recorded', true);

            $rowId = $request->attributes->get('rbac_audit_row');
            if ($rowId !== null) {
                AuditLog::where('id', $rowId)->update(['outcome' => 'blocked']);

                return;
            }

            $uri = $request->route()?->uri() ?? $request->path();
            $map = config('route_permission_map', []);
            $pattern = null;
            foreach ([$request->method() . ' ' . $uri, '* ' . $uri] as $key) {
                if (isset($map[$key])) {
                    $pattern = $key;
                    break;
                }
            }
            $rule = $pattern !== null ? $map[$pattern] : null;

            AuditLog::create([
                'user_id' => $request->user()->id,
                'org_id' => CurrentOrg::id($request->user()->id),
                'method' => $request->method(),
                'route_uri' => $uri,
                'matched_pattern' => $pattern,
                'permission_group' => $rule[0] ?? null,
                'required_level' => $rule[1] ?? null,
                'batch' => $rule['batch'] ?? null,
                'outcome' => 'blocked',
                'reason' => 'controller_denied',
            ]);
        } catch (\Throwable $e) {
            Log::error('denial recorder failed', ['error' => get_class($e)]);
        }
    }
}
