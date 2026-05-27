<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug',
    'name',
    'logo_path',
    'owner_id',
    'locale',
    'timezone',
    'currency',
    'preferred_gateway',
    'status',
    'settings',
    'trial_ends_at',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->using(TenantMembership::class)
            ->withPivot(['invited_by_id', 'joined_at', 'last_seen_at'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Whether the tenant's currently-active subscription includes the
     * given feature slug. Returns false when the tenant has no non-terminal
     * subscription (trialing/active/past_due) or the plan doesn't include it.
     *
     * Slug catalog lives in config('billing.features').
     *
     * Example: if ($tenant->hasFeature('api_access')) { ... }
     */
    public function hasFeature(string $slug): bool
    {
        return $this->currentPlan()?->hasFeature($slug) ?? false;
    }

    /**
     * Quota limit for a feature on the tenant's current plan.
     *
     * Returns:
     *   null when the feature is unlimited
     *   0    when no active subscription or the feature isn't included
     *   int  otherwise (the limit)
     */
    public function featureLimit(string $slug): ?int
    {
        $plan = $this->currentPlan();
        if ($plan === null) {
            return 0;
        }

        return $plan->featureLimit($slug);
    }

    /**
     * Whether the tenant can use one more of a quota feature given its
     * current count. True when the feature is unlimited or count < limit.
     *
     * Example:
     *   if (! $tenant->canUseMore('projects', $tenant->projects()->count())) {
     *       abort(403, 'Project limit reached on your plan.');
     *   }
     */
    public function canUseMore(string $slug, int $currentCount): bool
    {
        $limit = $this->featureLimit($slug);
        if ($limit === null) {
            return true; // unlimited
        }

        return $currentCount < $limit;
    }

    /**
     * The Plan from the tenant's currently-non-terminal subscription, if any.
     */
    public function currentPlan(): ?Plan
    {
        $subscription = $this->subscriptions()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->with('plan')
            ->first();

        return $subscription?->plan;
    }
}
