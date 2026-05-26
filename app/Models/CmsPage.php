<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug',
    'title',
    'locale',
    'body_markdown',
    'body_html',
    'status',
    'template',
    'meta_title',
    'meta_description',
    'og_image_path',
    'no_index',
    'published_at',
    'author_id',
])]
class CmsPage extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const TEMPLATE_DEFAULT = 'default';

    public const TEMPLATE_LANDING = 'landing';

    public const TEMPLATE_DOCS = 'docs';

    public const TEMPLATE_LEGAL = 'legal';

    protected function casts(): array
    {
        return [
            'no_index' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast();
    }
}
