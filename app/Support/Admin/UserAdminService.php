<?php

namespace App\Support\Admin;

use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\NotificationPreference;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Canonical service seam for Super Admin user mutations.
 *
 * Mirrors TenantAdminService: every action ends in an audit-log entry and
 * destructive mutations are wrapped in transactions. The Super Admin can be
 * called from background jobs too (e.g. breach-check auto-suspend).
 */
class UserAdminService
{
    public function suspend(User $user, User $admin, ?string $reason, ?Request $request = null): User
    {
        DB::transaction(function () use ($user) {
            $user->suspended_at = now();
            $user->save();

            // Drop existing DB-backed sessions for the suspended user.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        $this->record(
            $user,
            $admin,
            'admin.user.suspended',
            null,
            ['reason' => $reason],
            $request,
        );

        return $user->fresh();
    }

    public function restore(User $user, User $admin, ?Request $request = null): User
    {
        $user->suspended_at = null;
        $user->save();

        $this->record($user, $admin, 'admin.user.restored', null, null, $request);

        return $user->fresh();
    }

    public function resendVerification(User $user, User $admin, ?Request $request = null): void
    {
        if (! method_exists($user, 'sendEmailVerificationNotification')) {
            return;
        }
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();

        $this->record(
            $user,
            $admin,
            'admin.user.verification_resent',
            null,
            null,
            $request,
        );
    }

    public function forcePasswordReset(User $user, User $admin, ?Request $request = null): void
    {
        $user->force_password_reset = true;
        $user->save();

        Password::broker()->sendResetLink(['email' => $user->email]);

        $this->record(
            $user,
            $admin,
            'admin.user.force_password_reset',
            null,
            null,
            $request,
        );
    }

    public function disableTwoFactor(User $user, User $admin, ?Request $request = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->record(
            $user,
            $admin,
            'admin.user.2fa_disabled',
            null,
            null,
            $request,
        );
    }

    public function revokeSessions(User $user, User $admin, ?Request $request = null): int
    {
        $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->record(
            $user,
            $admin,
            'admin.user.sessions_revoked',
            null,
            ['count' => $deleted],
            $request,
        );

        return $deleted;
    }

    public function revokeTokens(User $user, User $admin, ?Request $request = null): int
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        $this->record(
            $user,
            $admin,
            'admin.user.tokens_revoked',
            null,
            ['count' => $count],
            $request,
        );

        return $count;
    }

    public function grantSuperAdmin(User $user, User $admin, ?Request $request = null): void
    {
        $user->assignRole('Super Admin');

        $this->record(
            $user,
            $admin,
            'admin.user.super_admin_granted',
            null,
            null,
            $request,
        );
    }

    public function revokeSuperAdmin(User $user, User $admin, ?Request $request = null): void
    {
        $user->removeRole('Super Admin');

        $this->record(
            $user,
            $admin,
            'admin.user.super_admin_revoked',
            null,
            null,
            $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function gdprExport(User $user, User $admin, ?Request $request = null): array
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => ['id' => $admin->id, 'email' => $admin->email],
            'user' => $user->toArray(),
            'roles' => $user->getRoleNames()->toArray(),
            'memberships' => $user->memberships()->with('tenant:id,slug,name')->get()->toArray(),
            'social_accounts' => SocialAccount::where('user_id', $user->id)->get()->toArray(),
            'notification_preferences' => NotificationPreference::where('user_id', $user->id)->get()->toArray(),
            'login_history' => LoginHistory::where('user_id', $user->id)
                ->latest('id')->limit(500)->get()->toArray(),
            'audit_log_as_actor' => AuditLog::where('user_id', $user->id)
                ->latest('id')->limit(500)->get()->toArray(),
        ];

        $this->record(
            $user,
            $admin,
            'admin.user.gdpr_exported',
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
        User $user,
        User $admin,
        string $action,
        ?array $old,
        ?array $new,
        ?Request $request,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => null,
            'user_id' => $admin->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512),
        ]);
    }
}
