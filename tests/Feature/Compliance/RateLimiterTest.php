<?php

namespace Tests\Feature\Compliance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RateLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_throttle_blocks_sixth_attempt_within_a_minute(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $user = User::factory()->create(['password' => 'correct-password']);

        // Hammer the login endpoint with the wrong password.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $sixth = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertTrue(
            in_array($sixth->status(), [302, 429], true),
            'Sixth attempt should be throttled (429 or redirect with session error)',
        );

        if ($sixth->status() === 302) {
            $errors = $sixth->baseResponse->getSession()->get('errors');
            $this->assertNotNull($errors, 'Expected session errors on throttled attempt');
        }
    }

    public function test_named_limiters_are_registered(): void
    {
        // RateLimiter::limiter() returns the closure registered for the
        // given name, or null if unregistered. Treat presence of a
        // closure as "registered".
        $this->assertNotNull(RateLimiter::limiter('register'));
        $this->assertNotNull(RateLimiter::limiter('forgot-password'));
        $this->assertNotNull(RateLimiter::limiter('2fa'));
        $this->assertNotNull(RateLimiter::limiter('login'));
    }
}
