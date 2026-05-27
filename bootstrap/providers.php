<?php

use App\Providers\AppServiceProvider;
use App\Providers\AppSettingsServiceProvider;
use App\Providers\CmsServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppSettingsServiceProvider::class,
    AppServiceProvider::class,
    CmsServiceProvider::class,
    FortifyServiceProvider::class,
];
