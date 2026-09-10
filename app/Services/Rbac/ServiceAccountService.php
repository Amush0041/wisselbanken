<?php

namespace App\Services\Rbac;

use App\Models\Rbac\ApiToken;
use App\Models\User;
use Carbon\Carbon;

/**
 * Manages API tokens for non-human callers (AI importers, integration services).
 *
 * This is the structural pull-forward from plan §4.3: retrofitting non-human authentication
 * into a live system is expensive and risky, so the mechanism is built now. A service account
 * is just a User record that holds the api_system role, authenticated via a hashed bearer
 * token rather than a session cookie.
 *
 * Token lifecycle:
 *  1. createToken() generates a random 64-hex plaintext, stores its SHA-256 hash, and returns
 *     the plaintext once — it is never retrievable again.
 *  2. resolveUser() hashes the incoming bearer token and looks it up; on success it updates
 *     last_used_at and returns the User.
 *  3. revokeToken() soft-disables the token; the row is kept for audit purposes.
 */
class ServiceAccountService
{
    /**
     * Create a new API token for $userId. Returns the plaintext token — store it securely,
     * as it cannot be retrieved after this call.
     */
    public function createToken(int $userId, string $name, ?Carbon $expiresAt = null): string
    {
        $plaintext = bin2hex(random_bytes(32)); // 64 hex chars

        ApiToken::create([
            'user_id'    => $userId,
            'name'       => $name,
            'token'      => hash('sha256', $plaintext),
            'expires_at' => $expiresAt,
            'is_active'  => true,
        ]);

        return $plaintext;
    }

    /**
     * Soft-revoke a token by ID. The row is preserved for audit history.
     */
    public function revokeToken(int $tokenId): void
    {
        ApiToken::where('id', $tokenId)->update(['is_active' => false]);
    }

    /**
     * Resolve a plaintext bearer token to the owning User, or null if the token is invalid,
     * expired, or revoked. Updates last_used_at on success.
     */
    public function resolveUser(string $plaintext): ?User
    {
        $hash = hash('sha256', $plaintext);

        $token = ApiToken::where('token', $hash)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($token === null) {
            return null;
        }

        $token->update(['last_used_at' => now()]);

        return User::find($token->user_id);
    }
}
