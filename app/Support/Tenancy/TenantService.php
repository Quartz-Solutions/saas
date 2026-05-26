<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantOwnerTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Canonical service for all tenant lifecycle mutations.
 *
 * Per CLAUDE.md "service-layer single seam": every cross-cutting tenant write
 * goes through this class. Direct table writes outside the service are bugs.
 */
class TenantService
{
    /**
     * Default roles created per tenant. Kept here so consumers (seeder,
     * service.create) don't drift.
     *
     * @var array<int, string>
     */
    public const ROLES = ['Owner', 'Admin', 'Member'];

    /**
     * Create a new tenant + initial owner membership + per-team roles.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $owner, array $attributes): Tenant
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Tenant name is required.');
        }

        $slug = $this->uniqueSlug($attributes['slug'] ?? null, $name);

        return DB::transaction(function () use ($owner, $attributes, $name, $slug) {
            $tenant = new Tenant;
            $tenant->forceFill([
                'name' => $name,
                'slug' => $slug,
                'owner_id' => $owner->id,
                'logo_path' => $attributes['logo_path'] ?? null,
                'locale' => $attributes['locale'] ?? config('app.locale', 'en'),
                'timezone' => $attributes['timezone'] ?? config('app.timezone', 'UTC'),
                'currency' => strtoupper((string) ($attributes['currency'] ?? 'USD')),
                'status' => 'active',
                'settings' => $attributes['settings'] ?? [],
            ])->save();

            $this->ensureTenantRoles($tenant);

            $membership = new TenantMembership;
            $membership->forceFill([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'invited_by_id' => null,
                'joined_at' => now(),
            ])->save();

            setPermissionsTeamId($tenant->id);
            $owner->assignRole('Owner');

            return $tenant->fresh();
        });
    }

    /**
     * Update a tenant's general settings. Slug change re-checks uniqueness.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tenant $tenant, array $attributes): Tenant
    {
        return DB::transaction(function () use ($tenant, $attributes) {
            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);
                if ($name === '') {
                    throw new InvalidArgumentException('Tenant name cannot be empty.');
                }
                $tenant->name = $name;
            }

            if (array_key_exists('slug', $attributes) && $attributes['slug'] !== null) {
                $slug = Str::slug((string) $attributes['slug']);
                if ($slug !== $tenant->slug) {
                    $tenant->slug = $this->uniqueSlug($slug, $tenant->name, $tenant->id);
                }
            }

            foreach (['logo_path', 'locale', 'timezone', 'currency', 'status', 'settings'] as $key) {
                if (array_key_exists($key, $attributes)) {
                    $tenant->{$key} = $key === 'currency'
                        ? strtoupper((string) $attributes[$key])
                        : $attributes[$key];
                }
            }

            $tenant->save();

            return $tenant->fresh();
        });
    }

    /**
     * Create an invitation token. If the email matches an existing user and
     * `$autoAttach` is true, the user is added immediately and a "joined"
     * invitation row is still recorded for the audit trail.
     */
    public function invite(
        Tenant $tenant,
        User $inviter,
        string $email,
        string $role = 'Member',
        bool $autoAttach = true,
    ): TenantInvitation {
        $email = strtolower(trim($email));

        if ($email === '') {
            throw new InvalidArgumentException('Invitation email is required.');
        }

        if (! in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException("Unknown role: {$role}");
        }

        return DB::transaction(function () use ($tenant, $inviter, $email, $role, $autoAttach) {
            $existing = TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $invitation = new TenantInvitation;
            $invitation->forceFill([
                'tenant_id' => $tenant->id,
                'invited_by_id' => $inviter->id,
                'email' => $email,
                'role' => $role,
                'token' => $this->freshInvitationToken(),
                'expires_at' => now()->addDays(7),
            ])->save();

            if ($autoAttach) {
                $user = User::query()->where('email', $email)->first();
                if ($user !== null && ! $this->isMember($tenant, $user)) {
                    $this->attachMember($tenant, $user, $role, $inviter);
                    $invitation->forceFill([
                        'accepted_at' => now(),
                        'accepted_by_id' => $user->id,
                    ])->save();
                }
            }

            return $invitation->fresh();
        });
    }

    /**
     * Accept an invitation by token. Creates the membership + role assignment.
     */
    public function acceptInvitation(string $token, User $user): TenantMembership
    {
        $invitation = TenantInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($invitation === null) {
            throw new RuntimeException('Invitation is invalid or has expired.');
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw new RuntimeException('Invitation belongs to a different email address.');
        }

        return DB::transaction(function () use ($invitation, $user) {
            $tenant = $invitation->tenant;

            if ($this->isMember($tenant, $user)) {
                $membership = TenantMembership::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            } else {
                $membership = $this->attachMember(
                    $tenant,
                    $user,
                    $invitation->role ?: 'Member',
                    $invitation->inviter ?: null,
                );
            }

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_id' => $user->id,
            ])->save();

            return $membership;
        });
    }

    /**
     * Revoke an unaccepted invitation.
     */
    public function revokeInvitation(TenantInvitation $invitation): void
    {
        if ($invitation->accepted_at !== null) {
            throw new RuntimeException('Cannot revoke an accepted invitation.');
        }

        $invitation->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Begin an owner transfer. The new owner must accept via the returned
     * token before the transfer takes effect.
     */
    public function transferOwnership(Tenant $tenant, User $currentOwner, User $newOwner): TenantOwnerTransfer
    {
        if ($tenant->owner_id !== $currentOwner->id) {
            throw new RuntimeException('Only the current owner can initiate an ownership transfer.');
        }

        if ($currentOwner->id === $newOwner->id) {
            throw new InvalidArgumentException('Cannot transfer ownership to the current owner.');
        }

        return DB::transaction(function () use ($tenant, $currentOwner, $newOwner) {
            if (! $this->isMember($tenant, $newOwner)) {
                $this->attachMember($tenant, $newOwner, 'Admin', $currentOwner);
            }

            $transfer = new TenantOwnerTransfer;
            $transfer->forceFill([
                'tenant_id' => $tenant->id,
                'current_owner_id' => $currentOwner->id,
                'new_owner_id' => $newOwner->id,
                'token' => $this->freshInvitationToken(),
                'expires_at' => now()->addDays(7),
            ])->save();

            return $transfer->fresh();
        });
    }

    /**
     * Accept an in-flight owner transfer. Swaps ownership + role assignments.
     */
    public function acceptOwnerTransfer(string $token, User $acceptor): Tenant
    {
        $transfer = TenantOwnerTransfer::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($transfer === null) {
            throw new RuntimeException('Ownership transfer is invalid or has expired.');
        }

        if ($transfer->new_owner_id !== $acceptor->id) {
            throw new RuntimeException('Only the designated new owner can accept this transfer.');
        }

        return DB::transaction(function () use ($transfer, $acceptor) {
            $tenant = $transfer->tenant;
            $previousOwner = $transfer->currentOwner;

            $tenant->forceFill(['owner_id' => $acceptor->id])->save();

            setPermissionsTeamId($tenant->id);

            if ($previousOwner !== null) {
                $previousOwner->syncRoles(['Admin']);
            }

            $acceptor->syncRoles(['Owner']);

            $transfer->forceFill(['accepted_at' => now()])->save();

            return $tenant->fresh();
        });
    }

    /**
     * Soft-delete the tenant. Memberships and roles remain so the 30-day
     * recovery window can restore them.
     */
    public function softDelete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->forceFill(['status' => 'pending_deletion'])->save();
            $tenant->delete();
        });
    }

    /**
     * Restore a soft-deleted tenant.
     */
    public function restore(Tenant $tenant): Tenant
    {
        $tenant->restore();
        $tenant->forceFill(['status' => 'active'])->save();

        return $tenant->fresh();
    }

    /**
     * Attach a user to a tenant with a given role. Does NOT bypass uniqueness.
     */
    private function attachMember(Tenant $tenant, User $user, string $role, ?User $inviter): TenantMembership
    {
        $this->ensureTenantRoles($tenant);

        $membership = new TenantMembership;
        $membership->forceFill([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'invited_by_id' => $inviter?->id,
            'joined_at' => now(),
        ])->save();

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        return $membership;
    }

    private function isMember(Tenant $tenant, User $user): bool
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Make sure the per-team default roles exist.
     */
    public function ensureTenantRoles(Tenant $tenant): void
    {
        setPermissionsTeamId($tenant->id);

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function uniqueSlug(?string $candidate, string $fallbackName, ?int $ignoreId = null): string
    {
        $base = Str::slug($candidate ?: $fallbackName);

        if ($base === '') {
            $base = 'tenant';
        }

        $slug = $base;
        $i = 2;

        while (Tenant::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->withTrashed()
            ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function freshInvitationToken(): string
    {
        do {
            $token = Str::random(64);
        } while (TenantInvitation::query()->where('token', $token)->exists()
            || TenantOwnerTransfer::query()->where('token', $token)->exists());

        return $token;
    }
}
