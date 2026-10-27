<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, B06: signup sends the verification e-mail exactly once, and a missing team size is reported inline
 * (server side and in the page script) instead of through a browser alert().
 */
class RegistrationTest extends RbacTestCase
{
    private function signup(array $over = [])
    {
        return $this->post('register', $over + [
            'name' => 'Fresh Owner', 'email' => 'fresh.owner@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'company_name' => 'Fresh Co', 'org_type' => 'subcontractor', 'team_size' => 'solo',
        ]);
    }

    public static function signups(): array
    {
        return [
            'solo buyer' => ['subcontractor', 'solo'],
            'small manufacturer' => ['manufacturer', '2-5'],
            'larger general contractor' => ['general_contractor', '20+'],
        ];
    }

    #[DataProvider('signups')]
    public function test_registration_sends_the_verification_mail_once(string $orgType, string $teamSize): void
    {
        Notification::fake();

        $this->signup(['org_type' => $orgType, 'team_size' => $teamSize])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'fresh.owner@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        Notification::assertCount(1);
    }

    public function test_the_verification_mail_goes_to_the_registered_address_and_carries_a_signed_link(): void
    {
        Notification::fake();
        $this->signup();

        $user = User::where('email', 'fresh.owner@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->actionUrl !== null
                && str_contains($mail->actionUrl, 'email/verify/' . $user->id)
                && str_contains($mail->actionUrl, 'signature=');
        });
    }

    public function test_two_signups_each_get_their_own_mail(): void
    {
        Notification::fake();

        $this->signup();
        auth()->logout();
        $this->signup(['email' => 'second.owner@example.test', 'name' => 'Second Owner', 'company_name' => 'Second Co']);

        foreach (['fresh.owner@example.test', 'second.owner@example.test'] as $email) {
            Notification::assertSentToTimes(User::where('email', $email)->firstOrFail(), VerifyEmail::class, 1);
        }
        Notification::assertCount(2);
    }

    public function test_the_resend_route_sends_one_mail_to_an_unverified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->post('email/resend')->assertRedirect();

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_a_failed_validation_creates_no_user_and_sends_no_mail(): void
    {
        Notification::fake();

        $this->signup(['team_size' => ''])->assertSessionHasErrors('team_size');

        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }

    public function test_a_missing_team_size_renders_the_inline_server_error_under_the_select(): void
    {
        $page = $this->followingRedirects()->from('register')->signup(['team_size' => ''])->assertOk();

        $page->assertSee('The team size field is required.');
        $html = (string) $page->getContent();
        $this->assertMatchesRegularExpression('/<select id="team_size"[^>]*class="[^"]*\bis-invalid\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/<span class="invalid-feedback">The team size field is required\.<\/span>/', $html);
    }

    public function test_the_register_page_validates_its_steps_without_browser_alerts(): void
    {
        $html = (string) $this->get('register')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\balert\s*\(/', $html);
        $this->assertStringContainsString('Please select a team size before continuing.', $html);
        $this->assertStringContainsString('Please select an organization type before continuing.', $html);
    }
}
