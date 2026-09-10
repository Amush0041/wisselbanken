<?php

namespace Tests\Feature\Rbac;

use App\Auth\ApiTokenGuard;
use App\Services\Rbac\ServiceAccountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceAccountTest extends RbacTestCase
{
    private ServiceAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceAccountService::class);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function makeUser(string $email): int
    {
        return DB::table('users')->insertGetId([
            'name'       => $email,
            'email'      => $email,
            'role'       => 'user',
            'password'   => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── ServiceAccountService ────────────────────────────────────────────────

    /** @test */
    public function valid_token_resolves_to_user(): void
    {
        $userId    = $this->makeUser('svc@test.com');
        $plaintext = $this->service->createToken($userId, 'AI Importer');

        $resolved = $this->service->resolveUser($plaintext);

        $this->assertNotNull($resolved);
        $this->assertSame($userId, $resolved->id);
    }

    /** @test */
    public function unknown_token_returns_null(): void
    {
        $this->assertNull($this->service->resolveUser('not-a-real-token'));
    }

    /** @test */
    public function revoked_token_returns_null(): void
    {
        $userId    = $this->makeUser('svc2@test.com');
        $plaintext = $this->service->createToken($userId, 'Revoke Me');

        $token = DB::table('api_tokens')->where('user_id', $userId)->first();
        $this->service->revokeToken($token->id);

        $this->assertNull($this->service->resolveUser($plaintext));
    }

    /** @test */
    public function expired_token_returns_null(): void
    {
        $userId    = $this->makeUser('svc3@test.com');
        $plaintext = $this->service->createToken(
            $userId,
            'Expired Token',
            Carbon::now()->subMinute(), // already expired
        );

        $this->assertNull($this->service->resolveUser($plaintext));
    }

    /** @test */
    public function non_expiring_token_resolves_indefinitely(): void
    {
        $userId    = $this->makeUser('svc4@test.com');
        $plaintext = $this->service->createToken($userId, 'No Expiry', null);

        $this->assertNotNull($this->service->resolveUser($plaintext));
    }

    /** @test */
    public function resolving_a_token_updates_last_used_at(): void
    {
        $userId    = $this->makeUser('svc5@test.com');
        $plaintext = $this->service->createToken($userId, 'Tracking Token');

        $before = DB::table('api_tokens')->where('user_id', $userId)->value('last_used_at');
        $this->assertNull($before);

        $this->service->resolveUser($plaintext);

        $after = DB::table('api_tokens')->where('user_id', $userId)->value('last_used_at');
        $this->assertNotNull($after);
    }

    /** @test */
    public function token_hash_is_never_the_plaintext(): void
    {
        $userId    = $this->makeUser('svc6@test.com');
        $plaintext = $this->service->createToken($userId, 'Hash Check');

        $stored = DB::table('api_tokens')->where('user_id', $userId)->value('token');

        $this->assertNotSame($plaintext, $stored);
        $this->assertSame(hash('sha256', $plaintext), $stored);
    }

    // ─── ApiTokenGuard ────────────────────────────────────────────────────────

    /** @test */
    public function guard_returns_user_for_valid_bearer_token(): void
    {
        $userId    = $this->makeUser('svc7@test.com');
        $plaintext = $this->service->createToken($userId, 'Guard Test');

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer ' . $plaintext);

        $guard = new ApiTokenGuard($this->service, $request);

        $this->assertNotNull($guard->user());
        $this->assertSame($userId, $guard->user()->id);
    }

    /** @test */
    public function guard_returns_null_when_no_token_present(): void
    {
        $request = Request::create('/api/test');
        $guard   = new ApiTokenGuard($this->service, $request);

        $this->assertNull($guard->user());
    }

    /** @test */
    public function guard_returns_null_for_invalid_bearer_token(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer invalid-token-value');

        $guard = new ApiTokenGuard($this->service, $request);

        $this->assertNull($guard->user());
    }
}
