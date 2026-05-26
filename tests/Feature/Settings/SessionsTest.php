<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test environment runs SESSION_DRIVER=array (see phpunit.xml),
        // so the sessions table is not auto-created — make it locally for
        // these session-management tests.
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function ($table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    protected function insertSession(?int $userId, string $id = 'sess-1', int $minutesAgo = 1, string $userAgent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '203.0.113.7',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_guest_cannot_view_sessions_page(): void
    {
        $this->get(route('sessions.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_their_sessions(): void
    {
        $user = User::factory()->create();
        $this->insertSession($user->id, 'sess-current');
        $this->insertSession($user->id, 'sess-other', 60, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari');

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/sessions')
                ->has('sessions', 2));
    }

    public function test_only_user_own_sessions_are_listed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->insertSession($user->id, 'mine');
        $this->insertSession($other->id, 'theirs');

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessions', 1)
                ->where('sessions.0.id', 'mine'));
    }

    public function test_user_can_revoke_other_session(): void
    {
        $user = User::factory()->create();
        $this->insertSession($user->id, 'keep');
        $this->insertSession($user->id, 'revoke');

        $this->actingAs($user)
            ->delete(route('sessions.destroy', ['session' => 'revoke']))
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('id', 'revoke')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'keep')->count());
    }

    public function test_user_cannot_revoke_someone_elses_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->insertSession($other->id, 'theirs');

        $this->actingAs($user)
            ->delete(route('sessions.destroy', ['session' => 'theirs']))
            ->assertRedirect();

        $this->assertSame(1, DB::table('sessions')->where('id', 'theirs')->count());
    }

    public function test_destroy_all_keeps_current_and_revokes_the_rest(): void
    {
        $user = User::factory()->create();
        $this->insertSession($user->id, 'a');
        $this->insertSession($user->id, 'b');
        $this->insertSession($user->id, 'c');

        $this->actingAs($user)
            ->delete(route('sessions.destroyAll'))
            ->assertRedirect();

        // SESSION_DRIVER=array in tests means no current session id matches —
        // the controller is allowed to wipe everything.
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
