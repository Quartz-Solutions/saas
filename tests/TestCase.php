<?php

namespace Tests;

use App\Support\Auth\PwnedPasswords;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            PwnedPasswords::API_BASE.'*' => Http::response('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA:1', 200),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app()->forgetInstance('currentTenant');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
