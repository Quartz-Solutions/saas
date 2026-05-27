<?php

namespace App\Support\Cms;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Registry of block types renderable on a CMS page.
 *
 * Bound as a singleton in CmsServiceProvider::register() and populated
 * from config('cms.blocks'). Mirrors the gateway/social registry pattern.
 *
 * Validation:
 *   $registry->validateTree($blocks) → throws ValidationException if any
 *   block has an unknown type or violates its declared attribute rules.
 */
class BlockTypeRegistry
{
    /**
     * @var array<string, BlockType>
     */
    private array $types = [];

    public function register(BlockType $type): self
    {
        $this->types[$type->id] = $type;

        return $this;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->types);
    }

    public function get(string $id): BlockType
    {
        if (! $this->has($id)) {
            throw new InvalidArgumentException("Block type [{$id}] is not registered.");
        }

        return $this->types[$id];
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->types);
    }

    /**
     * @return array<int, BlockType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    /**
     * Grouped block descriptors for the admin block-picker UI.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->types as $type) {
            $grouped[$type->group][] = $type->toArray();
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Validate a full block tree. Recurses into children if a block type
     * is a container. Unknown types and per-block attribute rule violations
     * raise ValidationException.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function validateTree(array $blocks, string $path = 'body_blocks'): void
    {
        foreach ($blocks as $index => $block) {
            $blockPath = "{$path}.{$index}";

            if (! is_array($block)) {
                Validator::make(
                    ['block' => $block],
                    ['block' => ['required', 'array']],
                    ['block.array' => "Block at {$blockPath} must be an object."],
                )->validate();
            }

            $type = (string) ($block['type'] ?? '');
            $registeredIds = $this->ids();
            Validator::make(
                ['type' => $type],
                ['type' => ['required', 'string', 'in:'.implode(',', $registeredIds)]],
                [
                    'type.in' => "Block at {$blockPath} has unknown type [{$type}].",
                    'type.required' => "Block at {$blockPath} is missing a type.",
                ],
            )->validate();

            $descriptor = $this->get($type);
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if ($descriptor->rules !== []) {
                // Flatten attribute rules onto a fresh `attrs` array so dotted
                // keys (e.g. `items.*.label`) resolve against actual nested
                // data rather than being treated as nested data keys.
                $rules = [];
                foreach ($descriptor->rules as $key => $rule) {
                    $rules['attrs.'.$key] = $rule;
                }
                Validator::make(['attrs' => $attrs], $rules)->validate();
            }

            if ($descriptor->isContainer && isset($block['children']) && is_array($block['children'])) {
                $this->validateTree($block['children'], $blockPath.'.children');
            }
        }
    }
}
