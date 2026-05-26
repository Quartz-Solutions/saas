<?php

namespace App\Jobs;

use App\Models\DataExportRequest;
use App\Models\LoginHistory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\DataExportReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Builds a ZIP containing the user's data: User record, TenantMemberships,
 * LoginHistory, notifications, plus tenant data they own.
 *
 * Writes to `storage/app/exports/{user_id}/{uuid}.zip` on the local disk.
 * On success, dispatches the DataExportReady notification (with a 24h
 * signed download link).
 */
class GenerateDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $exportRequestId) {}

    public function handle(): void
    {
        /** @var DataExportRequest|null $export */
        $export = DataExportRequest::query()->find($this->exportRequestId);
        if ($export === null) {
            return;
        }

        $user = User::query()->find($export->user_id);
        if ($user === null) {
            $export->update([
                'status' => 'failed',
                'error_message' => 'User not found.',
            ]);

            return;
        }

        $export->update(['status' => 'processing']);

        try {
            $relativePath = $this->buildArchive($user, $export);

            $disk = Storage::disk('local');
            $absolute = $disk->path($relativePath);
            $size = is_file($absolute) ? (int) filesize($absolute) : null;

            $export->update([
                'status' => 'ready',
                'file_path' => $relativePath,
                'file_size_bytes' => $size,
                'processed_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            $user->notify(new DataExportReady($export->refresh()));
        } catch (Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000, ''),
            ]);
            throw $e;
        }
    }

    protected function buildArchive(User $user, DataExportRequest $export): string
    {
        $disk = Storage::disk('local');
        $directory = 'exports/'.$user->id;
        $filename = (string) Str::uuid().'.zip';
        $relativePath = $directory.'/'.$filename;

        $disk->makeDirectory($directory);
        $absolute = $disk->path($relativePath);

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive at '.$absolute);
        }

        $zip->addFromString('user.json', $this->jsonEncode($user->toArray()));

        $memberships = TenantMembership::query()
            ->where('user_id', $user->id)
            ->get()
            ->toArray();
        $zip->addFromString('tenant_memberships.json', $this->jsonEncode($memberships));

        $loginHistory = LoginHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
        $zip->addFromString('login_history.json', $this->jsonEncode($loginHistory));

        $notifications = $user->notifications()->get()->toArray();
        $zip->addFromString('notifications.json', $this->jsonEncode($notifications));

        $ownedTenants = Tenant::query()
            ->where('owner_id', $user->id)
            ->withTrashed()
            ->get()
            ->toArray();
        $zip->addFromString('owned_tenants.json', $this->jsonEncode($ownedTenants));

        $zip->addFromString('README.txt', $this->readme($user, $export));

        $zip->close();

        return $relativePath;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function jsonEncode(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function readme(User $user, DataExportRequest $export): string
    {
        $now = now()->toDateTimeString();

        return <<<TXT
Data export for user #{$user->id} ({$user->email})
Generated: {$now}
Request:   #{$export->id}

Contents:
  user.json                 — your User record
  tenant_memberships.json   — your tenant memberships
  login_history.json        — recent sign-in activity
  notifications.json        — in-app notifications
  owned_tenants.json        — tenants you own (including soft-deleted)

This archive is provided to satisfy GDPR Article 20 (data portability)
and similar regulations. The download link expires 24 hours after
generation.
TXT;
    }
}
