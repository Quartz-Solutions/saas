<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * GDPR 30-day purge — hard-deletes any tenant that has been soft-deleted
 * for at least 30 days. Cascading FK constraints clean up memberships,
 * subscriptions, invoices, payments, etc.
 *
 * Each purge writes one AuditLog entry with a snapshot of the tenant's id +
 * slug + name so investigators can still trace the row id forensically
 * after the data itself is gone.
 *
 * Scheduled daily; see routes/console.php.
 */
class PurgeExpiredSoftDeletedTenants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RETENTION_DAYS = 30;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $purged = 0;

        Tenant::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($tenants) use (&$purged) {
                foreach ($tenants as $tenant) {
                    $snapshot = [
                        'id' => $tenant->id,
                        'slug' => $tenant->slug,
                        'name' => $tenant->name,
                        'deleted_at' => $tenant->deleted_at?->toIso8601String(),
                    ];

                    $tenant->forceDelete();

                    AuditLog::query()->create([
                        'tenant_id' => null, // row is gone
                        'user_id' => null,   // system action
                        'action' => 'system.tenant.gdpr_purged',
                        'auditable_type' => Tenant::class,
                        'auditable_id' => $snapshot['id'],
                        'old_values' => $snapshot,
                        'new_values' => null,
                        'context' => ['retention_days' => self::RETENTION_DAYS],
                    ]);

                    $purged++;
                }
            });

        return $purged;
    }
}
