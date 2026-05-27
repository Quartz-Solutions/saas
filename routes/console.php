<?php

use App\Jobs\ExpireStaleCheckouts;
use App\Jobs\PurgeExpiredSoftDeletedTenants;
use App\Jobs\SendCheckoutAbandonmentReminders;
use App\Jobs\SendTrialEndingReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Daily database backup
|--------------------------------------------------------------------------
| Runs `docker/scripts/backup-db.sh` once a day. The script pg_dumps the
| primary DB, gzips, then either uploads to `s3://${BACKUP_BUCKET}/db/<date>.sql.gz`
| (when `BACKUP_BUCKET` is set) or writes to `storage/backups/`. `onOneServer`
| guards against duplicate runs when more than one scheduler container is up.
*/
Schedule::exec(base_path('docker/scripts/backup-db.sh'))
    ->daily()
    ->onOneServer()
    ->name('db-backup')
    ->description('Daily Postgres dump -> S3 (or storage/backups/ when BACKUP_BUCKET is unset).');

/*
|--------------------------------------------------------------------------
| Expire stale checkout sessions
|--------------------------------------------------------------------------
| Marks any pending/awaiting_payment CheckoutSession past its expires_at
| as expired + fires CheckoutAbandoned. See agent-os/product/checkout.md §7.
*/
Schedule::job(new ExpireStaleCheckouts)
    ->everyFiveMinutes()
    ->onOneServer()
    ->name('expire-stale-checkouts');

/*
|--------------------------------------------------------------------------
| Checkout abandonment reminders (1h after start)
|--------------------------------------------------------------------------
| Notifies users who started a checkout but haven't completed it after an
| hour. The session is then expired automatically by ExpireStaleCheckouts
| when expires_at passes (default 2h via CHECKOUT_TIMEOUT_MINUTES=120).
*/
Schedule::job(new SendCheckoutAbandonmentReminders)
    ->everyTenMinutes()
    ->onOneServer()
    ->name('checkout-abandonment-reminders');

/*
|--------------------------------------------------------------------------
| Trial-ending reminders
|--------------------------------------------------------------------------
| Daily sweep for trialing subscriptions whose trial_ends_at is within the
| next 3 days. Deduped via metadata.trial_reminder_sent_at.
*/
Schedule::job(new SendTrialEndingReminders)
    ->dailyAt('09:00')
    ->onOneServer()
    ->name('trial-ending-reminders');

/*
|--------------------------------------------------------------------------
| GDPR 30-day purge
|--------------------------------------------------------------------------
| Hard-deletes tenants that have been soft-deleted for >= 30 days.
| Cascading FKs clean up memberships/subscriptions/invoices/payments.
| Each purge writes one audit_logs row with a forensic snapshot.
*/
Schedule::job(new PurgeExpiredSoftDeletedTenants)
    ->dailyAt('03:00')
    ->onOneServer()
    ->name('gdpr-tenant-purge');

/*
|--------------------------------------------------------------------------
| Publish scheduled CMS pages
|--------------------------------------------------------------------------
| Flips draft → published when `publish_at` rolls over, and published →
| archived when `unpublish_at` rolls over. M11.
*/
Schedule::command('cms:publish-scheduled')
    ->everyMinute()
    ->onOneServer()
    ->name('cms-publish-scheduled');
