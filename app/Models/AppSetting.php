<?php

namespace App\Models;

use App\Support\Admin\AppSettingsService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

#[Fillable(['group', 'key', 'value', 'is_secret', 'updated_by'])]
class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $invalidate = function (): void {
            try {
                app(AppSettingsService::class)->invalidate();
            } catch (Throwable) {
                // Container not booted yet — overrides won't be cached either.
            }
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    /**
     * Encrypted at rest when is_secret = true. We don't use Laravel's
     * built-in `encrypted` cast because is_secret is data-dependent.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                if (! $this->is_secret) {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (Throwable) {
                    return null;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                return $this->is_secret ? Crypt::encryptString($value) : $value;
            },
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
