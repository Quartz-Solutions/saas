<?php

namespace Tests\Feature\Compliance;

use App\Jobs\GenerateDataExport;
use App\Models\DataExportRequest;
use App\Models\LoginHistory;
use App\Models\User;
use App\Notifications\DataExportReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_privacy_page_renders(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('privacy.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('settings/privacy'));
    }

    public function test_store_endpoint_dispatches_job_and_creates_request(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        LoginHistory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('privacy.exports.store'))
            ->assertRedirect();

        $this->assertDatabaseHas('data_export_requests', [
            'user_id' => $user->id,
            'status' => 'ready', // sync queue ran the job
        ]);

        Notification::assertSentTo($user, DataExportReady::class);
    }

    public function test_job_builds_zip_with_expected_entries(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        LoginHistory::factory()->count(2)->create(['user_id' => $user->id]);

        $export = DataExportRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => null,
            'status' => 'requested',
            'format' => 'zip',
            'requested_ip' => '127.0.0.1',
        ]);

        (new GenerateDataExport($export->id))->handle();

        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->file_size_bytes);
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));

        $absolute = Storage::disk('local')->path($export->file_path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($absolute) === true);

        $expectedEntries = [
            'user.json',
            'tenant_memberships.json',
            'login_history.json',
            'notifications.json',
            'owned_tenants.json',
            'README.txt',
        ];

        foreach ($expectedEntries as $entry) {
            $this->assertNotFalse(
                $zip->locateName($entry),
                "Expected ZIP to contain {$entry}",
            );
        }

        $userJson = $zip->getFromName('user.json');
        $this->assertNotFalse($userJson);
        $decoded = json_decode((string) $userJson, true);
        $this->assertSame($user->email, $decoded['email'] ?? null);

        $zip->close();

        Notification::assertSentTo($user, DataExportReady::class);
    }

    public function test_download_requires_signed_url(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $export = DataExportRequest::query()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'format' => 'zip',
            'file_path' => 'exports/'.$user->id.'/test.zip',
            'expires_at' => now()->addHour(),
        ]);

        Storage::disk('local')->put($export->file_path, 'fake zip contents');

        // Unsigned hit
        $this->actingAs($user)
            ->get(route('privacy.exports.download', ['export' => $export->id]))
            ->assertStatus(403);
    }
}
