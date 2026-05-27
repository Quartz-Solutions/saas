<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug', 'name', 'fields', 'success_message', 'notify_email',
    'webhook_url', 'store_submissions', 'is_active',
])]
class CmsForm extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'store_submissions' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CmsFormSubmission::class);
    }
}
