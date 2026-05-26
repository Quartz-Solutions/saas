<?php

namespace Tests\Feature\Compliance;

use App\Rules\PasswordNotPwned;
use App\Support\Auth\PwnedPasswords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PwnedPasswordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wipe parent's default Http fake so each test can register its own.
        $this->app->forgetInstance(HttpFactory::class);
        Http::clearResolvedInstances();
    }

    public function test_rule_blocks_known_compromised_password(): void
    {
        $password = 'password123';
        $hash = strtoupper(sha1($password));
        $suffix = substr($hash, 5);

        // Closure-based fake bypasses any wildcard fake set in parent setUp,
        // because Http resolves stubs by checking the most-recently-added
        // callable first.
        Http::fake(fn () => Http::response($suffix.':4242', 200));

        $validator = Validator::make(
            ['password' => $password],
            ['password' => [new PasswordNotPwned]],
        );

        $this->assertTrue($validator->fails(), 'Expected pwned password to fail validation');
    }

    public function test_rule_allows_clean_password(): void
    {
        $password = 'gv5n8t6gv-fresh-key-not-in-corpus';

        // Different suffix → no match.
        Http::fake(fn () => Http::response('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA:1', 200));

        $validator = Validator::make(
            ['password' => $password],
            ['password' => [new PasswordNotPwned]],
        );

        $this->assertFalse(
            $validator->fails(),
            'Validator errors: '.json_encode($validator->errors()->all()),
        );
    }

    public function test_registration_rejects_pwned_password(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $password = 'password';
        $hash = strtoupper(sha1($password));
        $suffix = substr($hash, 5);

        Http::fake(fn () => Http::response($suffix.':99999', 200));

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'pwned-test@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_failopen_on_network_error(): void
    {
        Http::fake(fn () => Http::response('', 500));

        $service = new PwnedPasswords;

        $this->assertFalse($service->isCompromised('any-password'));
    }
}
