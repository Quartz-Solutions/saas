<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tracks "user wants to subscribe / pay" intent from the moment of the
 * plan click through to gateway-confirmed completion or abandonment.
 *
 * See agent-os/product/checkout.md for the full architecture.
 */
#[Fillable([
    'public_id',
    'user_id',
    'tenant_id',
    'plan_id',
    'intent',
    'status',
    'gateway',
    'gateway_session_id',
    'currency',
    'amount_cents',
    'result_kind',
    'result_payload',
    'subscription_id',
    'invoice_id',
    'expires_at',
    'completed_at',
    'canceled_at',
    'abandonment_reminder_sent_at',
    'cancel_reason',
    'metadata',
])]
class CheckoutSession extends Model
{
    use HasFactory;

    /**
     * @var array<int, string> Audited fields; AuditObserver writes one
     *                         audit_logs row per change to any of these. Result payload, metadata,
     *                         and gateway_session_id are intentionally NOT audited — they're noisy
     *                         and not the state-transition signal admins care about.
     */
    public static array $auditableFields = ['status', 'gateway', 'cancel_reason'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELED,
        self::STATUS_EXPIRED,
    ];

    public const INTENT_SUBSCRIPTION = 'subscription';

    public const INTENT_ONE_TIME = 'one_time';

    public const KIND_REDIRECT = 'redirect';

    public const KIND_FORM_POST = 'form_post';

    public const KIND_IFRAME = 'iframe';

    public const KIND_WIDGET = 'widget';

    public const KIND_KIOSK_REF = 'kiosk_ref';

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'result_payload' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (empty($session->public_id)) {
                $session->public_id = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
