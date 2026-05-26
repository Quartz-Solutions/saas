<?php

namespace Tests;

use App\Support\Auth\PwnedPasswords;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Phase 8 — keep the HaveIBeenPwned check from hitting the real
        // network in every test. Individual tests that need to exercise
        // the rule call Http::fake() themselves AFTER setUp() and that
        // override wins.
        Http::fake([
            PwnedPasswords::API_BASE.'*' => Http::response('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA:1', 200),
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
