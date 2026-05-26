<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\TenantOnboardingCompleteRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Marks the current tenant as onboarded by persisting
 * `settings.onboarded_at = now()`. Only the tenant owner can complete the
 * onboarding wizard.
 */
class TenantOnboardingController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function complete(TenantOnboardingCompleteRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['onboarded_at'] = now()->toIso8601String();

        $this->service->update($tenant, ['settings' => $settings]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome aboard!')]);

        return back();
    }

    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
