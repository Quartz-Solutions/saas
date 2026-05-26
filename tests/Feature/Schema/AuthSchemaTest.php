<?php

namespace Tests\Feature\Schema;

use App\Models\LoginHistory;
use App\Models\MagicLoginToken;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_account_links_to_user(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-12345',
        ]);

        $this->assertTrue($account->user->is($user));
        $this->assertCount(1, $user->socialAccounts);
    }

    public function test_social_provider_user_id_pair_is_unique(): void
    {
        SocialAccount::factory()->create([
            'provider' => 'google',
            'provider_user_id' => 'duplicate-id',
        ]);

        $this->expectException(QueryException::class);

        SocialAccount::factory()->create([
            'provider' => 'google',
            'provider_user_id' => 'duplicate-id',
        ]);
    }

    public function test_magic_login_token_has_expiry(): void
    {
        $token = MagicLoginToken::factory()->create();

        $this->assertNotNull($token->expires_at);
        $this->assertNull($token->consumed_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_login_history_records_outcome(): void
    {
        $user = User::factory()->create();
        $entry = LoginHistory::factory()->create([
            'user_id' => $user->id,
            'outcome' => 'succeeded',
        ]);

        $this->assertSame('succeeded', $entry->outcome);
        $this->assertTrue($entry->user->is($user));
    }

    public function test_login_history_allows_null_user_for_unknown_email(): void
    {
        $entry = LoginHistory::factory()->create([
            'user_id' => null,
            'email' => 'unknown@example.com',
            'outcome' => 'failed',
        ]);

        $this->assertNull($entry->user);
        $this->assertSame('unknown@example.com', $entry->email);
    }

    public function test_user_profile_columns_present_after_migration(): void
    {
        $user = User::factory()->create([
            'avatar_path' => 'avatars/x.png',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'last_login_ip' => '127.0.0.1',
        ]);

        $this->assertSame('ar', $user->fresh()->locale);
        $this->assertSame('Asia/Riyadh', $user->fresh()->timezone);
    }
}
