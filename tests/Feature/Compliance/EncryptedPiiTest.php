<?php

namespace Tests\Feature\Compliance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptedPiiTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_returned_as_cleartext_to_php(): void
    {
        $user = User::factory()->create();
        $user->phone = '+1-555-0100';
        $user->save();

        $this->assertSame('+1-555-0100', $user->fresh()->phone);
    }

    public function test_phone_is_stored_as_ciphertext_in_the_database(): void
    {
        $user = User::factory()->create();
        $user->phone = '+1-555-0100';
        $user->save();

        $raw = DB::table('users')->where('id', $user->id)->value('phone');

        $this->assertNotNull($raw);
        $this->assertNotSame('+1-555-0100', $raw);
        // Laravel's encrypted cast wraps the ciphertext in a base64 envelope
        // that's at least ~150 chars even for short inputs.
        $this->assertGreaterThan(50, strlen($raw));
    }

    public function test_null_phone_stays_null(): void
    {
        $user = User::factory()->create();
        $user->phone = null;
        $user->save();

        $this->assertNull($user->fresh()->phone);
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('phone'));
    }
}
