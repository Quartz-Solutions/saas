<?php

namespace App\Models;

use Database\Factories\ThemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'description',
    'is_active',
    'is_preset',
    'mode_hint',
    'tokens',
    'radius',
    'font_family',
    'custom_css_path',
    'compiled_css_path',
    'compiled_at',
    'preview_image_path',
    'created_by_id',
])]
class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Audited fields (mirror App\Support\Admin\AppSettingsService auditing).
     *
     * @var array<int, string>
     */
    public static array $auditableFields = ['name', 'is_active', 'font_family'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_active' => 'boolean',
            'is_preset' => 'boolean',
            'compiled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ThemeFont, $this>
     */
    public function fonts(): HasMany
    {
        return $this->hasMany(ThemeFont::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
