<?php

use App\Mail\EmailVerificationMail;
use App\Mail\LoginAlertMail;
use App\Mail\MagicLinkMail;
use App\Mail\PasswordResetMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\PlanChangedMail;
use App\Mail\TenantInviteMail;
use App\Mail\TrialEndingMail;
use App\Mail\TwoFactorRecoveryMail;
use App\Mail\WelcomeMail;

/*
|--------------------------------------------------------------------------
| Notification Events
|--------------------------------------------------------------------------
|
| The canonical list of notification events. Drives the preferences matrix
| UI in `/settings/notifications`, the `NotificationDispatcher` channel
| routing, and the Mailable resolution.
|
| Channels:
|   - email    → Mailable class delivered via the default mailer
|   - database → Laravel DatabaseNotification → in-app bell
|   - slack    → reserved (deferred to Phase 6.1)
|   - sms      → reserved (deferred to Phase 6.1)
|
| `mailable` is the App\Mail\* class to dispatch on the email channel.
| `database` flag determines whether an entry is written to the
| `notifications` table for the in-app bell.
*/

return [

    'channels' => [
        'email' => [
            'label' => 'Email',
            'description' => 'Send messages to your registered email address.',
            'enabled' => true,
        ],
        'database' => [
            'label' => 'In-app',
            'description' => 'Show a notification in the bell dropdown.',
            'enabled' => true,
        ],
        'slack' => [
            'label' => 'Slack',
            'description' => 'Deliver to a configured Slack webhook (deferred).',
            'enabled' => false,
        ],
        'sms' => [
            'label' => 'SMS',
            'description' => 'Send a text message (deferred).',
            'enabled' => false,
        ],
    ],

    /*
    | Events keyed by their slug. The slug is the canonical identifier
    | persisted in `notification_preferences.event_type` and used by
    | `NotificationDispatcher::send($user, $event, $data)`.
    */
    'events' => [

        'welcome' => [
            'label' => 'Welcome',
            'description' => 'Sent when you sign up for the first time.',
            'group' => 'account',
            'mailable' => WelcomeMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'email_verification' => [
            'label' => 'Email verification',
            'description' => 'Verify your email address.',
            'group' => 'account',
            'mailable' => EmailVerificationMail::class,
            'defaults' => ['email' => true, 'database' => false],
            'always_on' => true, // can't opt-out of transactional verification
        ],

        'password_reset' => [
            'label' => 'Password reset',
            'description' => 'Reset link for your account password.',
            'group' => 'account',
            'mailable' => PasswordResetMail::class,
            'defaults' => ['email' => true, 'database' => false],
            'always_on' => true,
        ],

        'magic_link' => [
            'label' => 'Magic link sign-in',
            'description' => 'Passwordless sign-in link.',
            'group' => 'account',
            'mailable' => MagicLinkMail::class,
            'defaults' => ['email' => true, 'database' => false],
            'always_on' => true,
        ],

        'two_factor_recovery' => [
            'label' => 'Two-factor recovery code used',
            'description' => 'Notify you when a 2FA recovery code is consumed.',
            'group' => 'security',
            'mailable' => TwoFactorRecoveryMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'tenant_invite' => [
            'label' => 'Tenant invitation',
            'description' => 'You have been invited to join a workspace.',
            'group' => 'tenancy',
            'mailable' => TenantInviteMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'payment_receipt' => [
            'label' => 'Payment receipt',
            'description' => 'Confirmation of a successful payment.',
            'group' => 'billing',
            'mailable' => PaymentReceiptMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'plan_changed' => [
            'label' => 'Plan changed',
            'description' => 'Your subscription plan has been updated.',
            'group' => 'billing',
            'mailable' => PlanChangedMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'trial_ending' => [
            'label' => 'Trial ending soon',
            'description' => 'Reminder that your trial period ends soon.',
            'group' => 'billing',
            'mailable' => TrialEndingMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'payment_failed' => [
            'label' => 'Payment failed',
            'description' => 'A scheduled payment could not be processed.',
            'group' => 'billing',
            'mailable' => PaymentFailedMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

        'login_alert' => [
            'label' => 'New device login alert',
            'description' => 'Notify you when your account signs in from a new device.',
            'group' => 'security',
            'mailable' => LoginAlertMail::class,
            'defaults' => ['email' => true, 'database' => true],
            'always_on' => false,
        ],

    ],

];
