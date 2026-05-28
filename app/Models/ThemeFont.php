<?php

namespace App\Models;

use Database\Factories\ThemeFontFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'theme_id',
    'family',
    'weight',
    'style',
    'format',
    'path',
    'original_filename',
    'size_bytes',
])]
class ThemeFont extends Model
{
    /** @use HasFactory<ThemeFontFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
