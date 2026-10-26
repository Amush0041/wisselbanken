<?php

namespace App\Models\Rbac;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'rbac_audit_logs';

    protected $fillable = [
        'user_id',
        'org_id',
        'method',
        'route_uri',
        'matched_pattern',
        'permission_group',
        'required_level',
        'project_id',
        'quote_id',
        'batch',
        'outcome',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Writes one business-event row. Deliberately not fail-soft: callers that must roll back with
     * the event (auto-approval) rely on the exception; fail-soft callers wrap it themselves.
     */
    public static function event(
        string $outcome,
        int $userId,
        ?int $orgId,
        ?string $group,
        ?string $level,
        string $reason,
        ?string $method = null,
        ?string $routeUri = null,
    ): void {
        $request = request();
        $route = $request->route();
        $cli = $route === null && app()->runningInConsole();

        static::create([
            'user_id' => $userId,
            'org_id' => $orgId,
            'method' => $method ?? ($cli ? 'CLI' : $request->method()),
            'route_uri' => $routeUri ?? ($cli ? 'artisan ' . ($_SERVER['argv'][1] ?? '') : ($route?->uri() ?? $request->path())),
            'permission_group' => $group,
            'required_level' => $level,
            'outcome' => $outcome,
            'reason' => Str::limit($reason, 255, ''),
        ]);
    }
}
