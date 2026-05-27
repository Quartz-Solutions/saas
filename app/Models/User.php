<?php

namespace App\Models;

use App\Support\Notifications\NotificationDispatcher;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'avatar_path',
    'locale',
    'timezone',
    'last_login_ip',
    'last_login_at',
    'last_seen_at',
    'suspended_at',
    'force_password_reset',
    'current_tenant_id',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Fields the AuditObserver is allowed to record changes to.
     * Everything else (password, locale, last_seen_at, etc.) stays out
     * of the audit log to limit noise + PII exposure.
     *
     * @var array<int, string>
     */
    public static array $auditableFields = [
        'name',
        'email',
        'email_verified_at',
        'current_tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'force_password_reset' => 'boolean',
            // PII encrypted at rest. The cast handles encrypt-on-set +
            // decrypt-on-get transparently — `$user->phone = '...'` and
            // reads work as normal in PHP, the DB column stores the
            // ciphertext. Add more `'<col>' => 'encrypted'` lines below
            // for any other PII column you add (note: the column type
            // must be TEXT-sized because the ciphertext is much longer
            // than the cleartext).
            'phone' => 'encrypted',
        ];
    }

    /**
     * Auto-clear the `force_password_reset` flag whenever the password is
     * changed. Lets the EnforcePasswordReset middleware lift the lock-out
     * the moment the user picks a new password — whether they did so via
     * the email reset link or the in-app security settings.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            // Only auto-clear on UPDATE. The "password is dirty on create"
            // case would otherwise erase any flag a factory/seeder/admin
            // action explicitly set on a brand-new user row.
            if ($user->exists
                && $user->isDirty('password')
                && (bool) $user->force_password_reset
            ) {
                $user->force_password_reset = false;
            }
        });
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->using(TenantMembership::class)
            ->withPivot(['invited_by_id', 'joined_at', 'last_seen_at'])
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Override Laravel's default password-reset notification so we route
     * through NotificationDispatcher → PasswordResetMail. Keeps every
     * outbound email going through the same canonical seam.
     */
    public function sendPasswordResetNotification($token): void
    {
        app(NotificationDispatcher::class)
            ->send($this, 'password_reset', [
                'token' => $token,
                'resetUrl' => url(
                    route('password.reset', [
                        'token' => $token,
                        'email' => $this->email,
                    ], false),
                ),
            ]);
    }

    /**
     * Override Laravel's default email-verification notification so we
     * route through NotificationDispatcher → EmailVerificationMail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ],
        );

        app(NotificationDispatcher::class)
            ->send($this, 'email_verification', [
                'verifyUrl' => $verifyUrl,
            ]);
    }
}
