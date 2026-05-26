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

        $tenants = $user
            ? $user->tenants()
                ->withCount('memberships')
                ->orderBy('name')
                ->get()
                ->map(fn (Tenant $t) => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'role' => $t->owner_id === $user->id ? 'Owner' : 'Member',
                    'status' => $t->status,
                    'memberships_count' => $t->memberships_count,
                    'created_at' => $t->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('account/tenants', [
            'tenants' => $tenants,
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
