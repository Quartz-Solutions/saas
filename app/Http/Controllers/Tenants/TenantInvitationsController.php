<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\TenantInvitationDestroyRequest;
use App\Http\Requests\Tenants\TenantInvitationStoreRequest;
use App\Http\Requests\Tenants\TenantInvitationUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
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
     * Accept a tenant invitation.
     *
     * Three branches:
     *  1. Token invalid / expired / revoked / already-used → friendly page.
     *  2. No auth user → "invitation pending — sign in to accept" landing
     *     with sign-in + sign-up CTAs prefilled with the invitee email.
     *     The intended URL is stashed so post-auth they land back here.
     *  3. Auth user matches → accept + redirect to tenant dashboard.
     */
    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $user = $request->user();

        // Look the invitation up first so we can show the right page for
        // unauthenticated visitors AND for invalid tokens.
        $invitation = TenantInvitation::query()
            ->where('token', $token)
            ->with('tenant:id,slug,name,logo_path')
            ->first();

        // Token doesn't exist OR is in a non-acceptable state — show the
        // invalid page (works for both auth + guest visitors).
        if ($invitation === null
            || $invitation->revoked_at !== null
            || $invitation->accepted_at !== null
            || ($invitation->expires_at !== null && $invitation->expires_at->isPast())
        ) {
            $reason = $this->classifyInvitationFailure($token, $user, 'Invitation is invalid or has expired.');

            return Inertia::render('account/invitation-invalid', [
                'reason' => $reason['reason'],
                'title' => $reason['title'],
                'message' => $reason['message'],
                'tenant' => $reason['tenant'],
                'invitedEmail' => $reason['invitedEmail'],
            ]);
        }

        // Guest — render a landing page with sign-in/sign-up CTAs prefilled
        // with the invitee's email. Stash the intended URL so the existing
        // `redirect()->intended(...)` path in the auth controllers brings
        // them back here.
        if ($user === null) {
            $session = $request->hasSession() ? $request->session() : null;
            $session?->put('url.intended', url($request->fullUrl()));

            $hasAccount = User::query()
                ->where('email', $invitation->email)
                ->exists();

            return Inertia::render('account/invitation-pending', [
                'tenant' => [
                    'id' => $invitation->tenant?->id,
                    'slug' => $invitation->tenant?->slug,
                    'name' => $invitation->tenant?->name,
                    'logo_path' => $invitation->tenant?->logo_path,
                ],
                'invitedEmail' => $invitation->email,
                'role' => $invitation->role,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
                'hasAccount' => $hasAccount,
                'loginUrl' => route('login', ['email' => $invitation->email]),
                'registerUrl' => route('register', ['email' => $invitation->email]),
            ]);
        }

        // Authenticated user — try the accept; classify on failure (the
        // only remaining failure here is wrong_email, since we pre-checked
        // the invitation's state above).
        try {
            $membership = $this->service->acceptInvitation($token, $user);
        } catch (\RuntimeException $e) {
            $reason = $this->classifyInvitationFailure($token, $user, $e->getMessage());

            return Inertia::render('account/invitation-invalid', [
                'reason' => $reason['reason'],
                'title' => $reason['title'],
                'message' => $reason['message'],
                'tenant' => $reason['tenant'],
                'invitedEmail' => $reason['invitedEmail'],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to the tenant.')]);

        return to_route('tenants.dashboard', ['tenantSlug' => $membership->tenant->slug]);
    }

    /**
     * Best-effort classification of why an invitation token didn't accept,
     * so the UI can show a specific message (expired vs wrong-email vs
     * already-used) without leaking the existence of unrelated tokens.
     *
     * @return array{reason:string,title:string,message:string,tenant:?array<string,mixed>,invitedEmail:?string}
     */
    private function classifyInvitationFailure(string $token, User $user, string $serviceMessage): array
    {
        $row = TenantInvitation::query()
            ->where('token', $token)
            ->with('tenant:id,slug,name,logo_path')
            ->first();

        $tenant = $row?->tenant ? [
            'id' => $row->tenant->id,
            'slug' => $row->tenant->slug,
            'name' => $row->tenant->name,
            'logo_path' => $row->tenant->logo_path,
        ] : null;

        if ($row === null) {
            return [
                'reason' => 'not_found',
                'title' => __('Invitation not found'),
                'message' => __('This invitation link is invalid. It may have been mistyped or removed.'),
                'tenant' => null,
                'invitedEmail' => null,
            ];
        }

        if ($row->revoked_at !== null) {
            return [
                'reason' => 'revoked',
                'title' => __('Invitation revoked'),
                'message' => __('This invitation was withdrawn by the workspace owner. Ask them to send a new one.'),
                'tenant' => $tenant,
                'invitedEmail' => $row->email,
            ];
        }

        if ($row->accepted_at !== null) {
            return [
                'reason' => 'already_accepted',
                'title' => __('Invitation already used'),
                'message' => __('This invitation has already been accepted. You\'re likely already a member of this workspace.'),
                'tenant' => $tenant,
                'invitedEmail' => $row->email,
            ];
        }

        if ($row->expires_at !== null && $row->expires_at->isPast()) {
            return [
                'reason' => 'expired',
                'title' => __('Invitation expired'),
                'message' => __('This invitation link has expired. Ask the workspace owner to send a new one.'),
                'tenant' => $tenant,
                'invitedEmail' => $row->email,
            ];
        }

        if (strtolower($user->email) !== strtolower($row->email)) {
            return [
                'reason' => 'wrong_email',
                'title' => __('Wrong account'),
                'message' => __('This invitation was sent to a different email address. Sign in with the account that received the invite.'),
                'tenant' => $tenant,
                'invitedEmail' => $row->email,
            ];
        }

        return [
            'reason' => 'invalid',
            'title' => __('Invitation unavailable'),
            'message' => $serviceMessage,
            'tenant' => $tenant,
            'invitedEmail' => $row->email,
        ];
    }

    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
