<?php

namespace App\Http\Requests\Admin\Themes;

/**
 * Same shape as the store request — slug is auto-derived + immutable, and a
 * theme name need not be unique, so create/update validate identically.
 */
class ThemeUpdateRequest extends ThemeStoreRequest {}
