<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug',
    'title',
    'locale',
    'parent_id',
    'path',
    'route_name',
    'body_markdown',
    'body_html',
    'body_blocks',
    'status',
    'template',
    'meta_title',
    'meta_description',
    'og_image_path',
    'no_index',
    'published_at',
    'publish_at',
    'unpublish_at',
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
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'body_blocks' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast()
            && ($this->unpublish_at === null || $this->unpublish_at->isFuture());
    }

    /**
     * True when this page has been migrated to the block-based editor.
     * Legacy pages still rendered from body_html until first save in M2.
     */
    public function hasBlocks(): bool
    {
        return is_array($this->body_blocks) && $this->body_blocks !== [];
    }
}
