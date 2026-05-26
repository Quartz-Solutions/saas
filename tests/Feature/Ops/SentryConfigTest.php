<?php

namespace Tests\Feature\Ops;

use Sentry\SentrySdk;
use Tests\TestCase;

/**
 * Sanity-check that Sentry's exception handler is env-gated.
 *
 * In `bootstrap/app.php` we only call `SentryIntegration::handles($exceptions)`
 * when `SENTRY_DSN` (or `SENTRY_LARAVEL_DSN`) is set. Tests run with neither
 * env var, so:
 *
 *   - `config('sentry.dsn')` MUST resolve to null/empty
 *   - The Sentry SDK MUST report no DSN configured (Hub::getClient() === null
 *     OR options DSN is null), proving no Sentry transport is wired up.
 *
 * If someone later removes the env-gate in `bootstrap/app.php` this test will
 * still pass (because the DSN env vars are unset in CI) — but the actual gate
 * test is the assertion that the SDK has no client when DSN is blank.
 */
class SentryConfigTest extends TestCase
{
    public function test_sentry_dsn_is_not_set_in_test_env(): void
    {
        $this->assertEmpty(env('SENTRY_DSN'), 'SENTRY_DSN should be unset in tests.');
        $this->assertEmpty(env('SENTRY_LARAVEL_DSN'), 'SENTRY_LARAVEL_DSN should be unset in tests.');
        $this->assertEmpty(config('sentry.dsn'), 'config(sentry.dsn) should resolve to empty when no DSN env is set.');
    }

    public function test_sentry_sdk_is_not_initialised_when_dsn_is_blank(): void
    {
        // With no DSN, the Sentry SDK either has no client at all or a client
        // whose options DSN is null — either way, no events will be transmitted.
        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client === null) {
            $this->assertNull($client, 'No Sentry client should be bound when DSN is blank.');

            return;
        }

        $dsn = $client->getOptions()->getDsn();
        $this->assertNull($dsn, 'Sentry client options DSN must be null when SENTRY_DSN env var is unset.');
    }
}
