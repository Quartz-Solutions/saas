<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\TenantDestroyRequest;
use App\Http\Requests\Tenants\TenantStoreRequest;
use App\Http\Requests\Tenants\TenantUpdateRequest;
use App\Models\Currency;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Support\ImageProcessor;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TenantsController extends Controller
{
    public function __construct(
        private readonly TenantService $service,
        private readonly ImageProcessor $images,
    ) {}

    /**
     * Personal index — the tenants this user owns or belongs to.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Inertia::render('account/tenants', ['tenants' => []]);
        }

        $tenants = $user->tenants()
            ->withCount('memberships')
            ->orderBy('name')
            ->get();

        $tenantIds = $tenants->pluck('id')->all();

        // Active subscription per tenant (any non-terminal status).
        $activeSubs = \App\Models\Subscription::query()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->with('plan:id,slug,name,price_cents,currency,billing_period')
            ->get()
            ->keyBy('tenant_id');

        // Owner-only: pending invitations count per owned tenant.
        $ownedIds = $tenants->where('owner_id', $user->id)->pluck('id')->all();
        $pendingInvites = \App\Models\TenantInvitation::query()
            ->whereIn('tenant_id', $ownedIds)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        $payload = $tenants->map(function (Tenant $t) use ($user, $activeSubs, $pendingInvites) {
            $isOwner = $t->owner_id === $user->id;
            $sub = $activeSubs->get($t->id);
            /** @var \App\Models\TenantMembership|null $pivot */
            $pivot = $t->pivot ?? null;

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'logo_path' => $t->logo_path,
                'logo_url' => $t->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($t->logo_path)
                    : null,
                'role' => $isOwner ? 'Owner' : 'Member',
                'status' => $t->status,
                'currency' => $t->currency,
                'trial_ends_at' => $t->trial_ends_at?->toIso8601String(),
                'memberships_count' => (int) $t->memberships_count,
                'created_at' => $t->created_at?->toIso8601String(),
                'last_seen_at' => $pivot?->last_seen_at?->toIso8601String(),
                'plan' => $sub?->plan ? [
                    'slug' => $sub->plan->slug,
                    'name' => $sub->plan->name,
                    'price_cents' => (int) $sub->plan->price_cents,
                    'currency' => $sub->plan->currency,
                    'billing_period' => $sub->plan->billing_period,
                    'subscription_status' => $sub->status,
                ] : null,
                'pending_invites_count' => $isOwner
                    ? (int) ($pendingInvites[$t->id] ?? 0)
                    : null,
            ];
        })->values()->all();

        return Inertia::render('account/tenants', [
            'tenants' => $payload,
        ]);
    }

    public function store(TenantStoreRequest $request): RedirectResponse
    {
        $tenant = $this->service->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant created.')]);

        return to_route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
    }

    /**
     * Tenant settings page.
     */
    public function edit(Request $request): Response
    {
        $tenant = $this->currentTenant();
        $user = $request->user();
        $isOwner = $user !== null && $tenant->owner_id === $user->id;

        $invitations = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (TenantInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('tenants/settings', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'logo_path' => $tenant->logo_path,
                'logo_url' => $tenant->logo_path
                    ? Storage::disk('public')->url($tenant->logo_path)
                    : null,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'locale' => $tenant->locale,
                'status' => $tenant->status,
                'is_owner' => $isOwner,
            ],
            'invitations' => $invitations,
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->toArray(),
        ]);
    }

    public function update(TenantUpdateRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $attrs = $request->safe()->except(['logo']);

        if ($request->hasFile('logo')) {
            $oldPath = $tenant->logo_path;
            $attrs['logo_path'] = $this->images->store($request->file('logo'), 'tenants/'.$tenant->id);
            if ($oldPath !== null) {
                $this->images->delete($oldPath);
            }
        }

        $updated = $this->service->update($tenant, $attrs);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant updated.')]);

        return to_route('tenants.settings', ['tenantSlug' => $updated->slug]);
    }

    public function destroy(TenantDestroyRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $this->service->softDelete($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant scheduled for deletion.')]);

        return to_route('account.tenants.index');
    }

    /**
     * Personal-scope soft-delete — invoked from /account/tenants card menus
     * without first switching into the tenant context. Owner-only.
     */
    public function destroyFromAccount(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_id === $request->user()?->id, 403);

        $this->service->softDelete($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant scheduled for deletion.')]);

        return to_route('account.tenants.index');
    }

    /**
     * Resolve the tenant currently scoped by `SetCurrentTenant` middleware.
     * Throws 404 when the middleware did not bind one.
     */
    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
