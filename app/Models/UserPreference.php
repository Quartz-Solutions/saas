<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'page',
        'name',
        'value',
        'is_active',
    ];

    protected $casts = [
        'value' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the active preset for a user on a given page.
     */
    public static function getActive(int|string $userId, string $page): ?self
    {
        return static::where('user_id', $userId)
            ->where('page', $page)
            ->where('is_active', true)
            ->first();
    }
}
