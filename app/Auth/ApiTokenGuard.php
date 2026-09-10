<?php

namespace App\Auth;

use App\Services\Rbac\ServiceAccountService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Stateless bearer-token guard for service account / non-human callers.
 *
 * Registered as the 'api_token' guard in config/auth.php. API routes that require a
 * machine identity use 'auth:api_token' middleware. The guard reads the Authorization
 * header, hashes the token, looks it up via ServiceAccountService, and returns the
 * associated User — at which point the normal RBAC check (checkPermission with the
 * api_system role) applies exactly as it would for any session-authenticated user.
 */
class ApiTokenGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private readonly ServiceAccountService $service,
        private readonly Request $request,
    ) {
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if ($token === null) {
            return null;
        }

        return $this->user = $this->service->resolveUser($token);
    }

    /**
     * Token-only auth — credential-based validation is not applicable.
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }
}
