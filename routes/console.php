<?php

use App\Jobs\ExpireStaleCheckouts;
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
