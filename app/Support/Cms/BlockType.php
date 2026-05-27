<?php

namespace App\Support\Cms;

/**
 * Immutable descriptor of a block type registered in the BlockTypeRegistry.
 *
 * The registry is the single source of truth for which block types are
 * renderable and what attribute schema each accepts. Validation reads
 * `rules()` to validate a saved page's `body_blocks` jsonb.
 */
final class BlockType
{
    /**
     * @param  string  $id  unique kebab-case identifier (matches block.type)
     * @param  string  $label  admin UI label
     * @param  string  $group  admin UI grouping in the block picker
     * @param  string  $icon  lucide icon name
     * @param  array<string, mixed>  $defaultAttrs  attributes for a freshly-inserted block
     * @param  array<string, string|array<int, string>>  $rules  Laravel validation rules keyed by attribute path
     * @param  string  $description  admin UI help text
     * @param  bool  $isContainer  true if children[] is allowed
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $group,
        public readonly string $icon,
        public readonly array $defaultAttrs = [],
        public readonly array $rules = [],
        public readonly string $description = '',
        public readonly bool $isContainer = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'group' => $this->group,
            'icon' => $this->icon,
            'defaultAttrs' => (object) $this->defaultAttrs,
            'description' => $this->description,
            'isContainer' => $this->isContainer,
        ];
    }
}
