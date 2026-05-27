<?php

namespace App\Support\Admin;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\LoginHistory;
use App\Models\OutboundWebhook;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Canonical service seam for Super Admin tenant mutations.
 *
 * Every controller action on a tenant routes through here so we can:
 *  - emit a single audit-log entry per mutation,
 *  - keep destructive operations atomic,
 *  - re-use the same primitives from background jobs (e.g. dunning auto-suspend).
 *
 * Per CLAUDE.md "service-layer single seam" pattern.
 */
class TenantAdminService
{
    public function suspend(Tenant $tenant, User $admin, ?string $reason, ?Request $request = null): Tenant
    {
        $previous = $tenant->status;

        DB::transaction(function () use ($tenant) {
            $tenant->status = 'suspended';
            $tenant->save();
        });

        $this->record(
            $tenant,
            $admin,
            'admin.tenant.suspended',
            ['status' => $previous],
            ['status' => 'suspended', 'reason' => $reason],
            $request,
        );

        return $tenant->fresh();
    }

    public function restore(Tenant $tenant, User $admin, ?Request $request = null): Tenant
    {
        DB::transaction(function () use ($tenant) {
            if ($tenant->trashed()) {
                $tenant->restore();
            }
            if ($tenant->status === 'suspended') {
                $tenant->status = 'active';
                $tenant->save();
            }
        });

        $this->record(
            $tenant,
            $admin,
            'admin.tenant.restored',
            null,
            ['status' => $tenant->status],
            $request,
        );

        return $tenant->fresh();
    }

    public function softDelete(Tenant $tenant, User $admin, ?string $reason, ?Request $request = null): void
    {
        if ($tenant->trashed()) {
            return;
        }

        DB::transaction(function () use ($tenant) {
            $tenant->delete();
        });

        $this->record(
            $tenant,
            $admin,
            'admin.tenant.deleted',
            null,
            ['reason' => $reason],
            $request,
        );
    }

    public function forceDelete(Tenant $tenant, User $admin, ?Request $request = null): void
    {
        $snapshot = [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
        ];

        DB::transaction(function () use ($tenant) {
            $tenant->forceDelete();
        });

        // The tenant row is gone, so we attach the audit entry without
        // the foreign key. The auditable_id is preserved for forensic lookup.
        AuditLog::query()->create([
            'tenant_id' => null,
            'user_id' => $admin->id,
            'action' => 'admin.tenant.force_deleted',
            'auditable_type' => Tenant::class,
            'auditable_id' => $snapshot['id'],
            'old_values' => $snapshot,
            'new_values' => null,
            'context' => ['gdpr_purge' => true],
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512),
        ]);
    }

    /**
     * Build a JSON document containing every record we hold on the tenant.
     *
     * @return array<string, mixed>
     */
    public function gdprExport(Tenant $tenant, User $admin, ?Request $request = null): array
    {
        $tenant->load(['owner', 'memberships.user']);

        $userIds = $tenant->memberships->pluck('user_id')->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => ['id' => $admin->id, 'email' => $admin->email],
            'tenant' => $tenant->toArray(),
            'owner' => $tenant->owner?->only(['id', 'name', 'email', 'created_at']),
            'memberships' => $tenant->memberships->map(fn (TenantMembership $m) => [
                'user' => $m->user?->only(['id', 'name', 'email']),
                'joined_at' => $m->joined_at?->toIso8601String(),
            ])->all(),
            'subscriptions' => Subscription::where('tenant_id', $tenant->id)->get()->toArray(),
            'invoices' => Invoice::where('tenant_id', $tenant->id)->get()->toArray(),
            'payments' => Payment::where('tenant_id', $tenant->id)->get()->toArray(),
            'webhook_events' => WebhookEvent::where('tenant_id', $tenant->id)
                ->latest('id')->limit(500)->get()->toArray(),
            'outbound_webhooks' => OutboundWebhook::where('tenant_id', $tenant->id)->get()->toArray(),
            'audit_log' => AuditLog::where('tenant_id', $tenant->id)
                ->latest('id')->limit(1000)->get()->toArray(),
            'login_history' => LoginHistory::whereIn('user_id', $userIds)
                ->latest('id')->limit(500)->get()->toArray(),
        ];

        $this->record(
            $tenant,
            $admin,
            'admin.tenant.gdpr_exported',
            null,
            ['rows' => count($payload, COUNT_RECURSIVE)],
            $request,
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function record(
        Tenant $tenant,
        User $admin,
        string $action,
        ?array $old,
        ?array $new,
        ?Request $request,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => $action,
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512),
        ]);
    }
}
