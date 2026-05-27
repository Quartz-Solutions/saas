<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug', 'title', 'locale', 'excerpt', 'cover_image_url',
    'body_blocks', 'body_html', 'status', 'published_at',
    'author_id', 'reading_minutes', 'no_index',
    'meta_title', 'meta_description',
])]
class CmsBlogPost extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'body_blocks' => 'array',
            'published_at' => 'datetime',
            'no_index' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CmsBlogCategory::class, 'cms_blog_post_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CmsBlogTag::class, 'cms_blog_post_tag');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast();
    }
}
