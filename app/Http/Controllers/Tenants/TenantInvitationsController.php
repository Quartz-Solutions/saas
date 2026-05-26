<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\TenantInvitationDestroyRequest;
use App\Http\Requests\Tenants\TenantInvitationStoreRequest;
use App\Http\Requests\Tenants\TenantInvitationUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantInvitationsController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant();

        $invitations = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (TenantInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'token' => $i->token,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'accepted_at' => $i->accepted_at?->toIso8601String(),
                'revoked_at' => $i->revoked_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('tenants/invitations', [
            'invitations' => $invitations,
        ]);
    }

    public function store(TenantInvitationStoreRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        $this->service->invite(
            $tenant,
            $request->user(),
            (string) $request->string('email'),
            (string) $request->string('role'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function update(TenantInvitationUpdateRequest $request, string $tenantSlug, TenantInvitation $invitation): RedirectResponse
    {
        unset($tenantSlug); // route binding consumed by `tenant` middleware
        $tenant = $this->currentTenant();
        abort_if($invitation->tenant_id !== $tenant->id, 404);

        $invitation->forceFill(['role' => (string) $request->string('role')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation updated.')]);

        return back();
    }

    public function destroy(TenantInvitationDestroyRequest $request, string $tenantSlug, TenantInvitation $invitation): RedirectResponse
    {
        unset($tenantSlug);
        $tenant = $this->currentTenant();
        abort_if($invitation->tenant_id !== $tenant->id, 404);

        $this->service->revokeInvitation($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }

    /**
     * Accept a tenant invitation. Public-ish: requires only that the
     * authenticated user matches the invited email.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $membership = $this->service->acceptInvitation($token, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to the tenant.')]);

        return to_route('tenants.dashboard', ['tenantSlug' => $membership->tenant->slug]);
    }

    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
