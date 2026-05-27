<?php

namespace App\Providers;

use App\Support\Cms\BlockType;
use App\Support\Cms\BlockTypeRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the CMS block-type registry from config('cms.blocks').
 *
 * Each entry in the config array becomes a `BlockType` and is registered
 * in the singleton `BlockTypeRegistry`. Controllers and FormRequests
 * resolve through the registry — never construct BlockType instances
 * inline.
 */
class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockTypeRegistry::class, function () {
            $registry = new BlockTypeRegistry;

            foreach ((array) config('cms.blocks', []) as $entry) {
                $registry->register(new BlockType(
                    id: (string) $entry['id'],
                    label: (string) $entry['label'],
                    group: (string) ($entry['group'] ?? 'Other'),
                    icon: (string) ($entry['icon'] ?? 'box'),
                    defaultAttrs: (array) ($entry['defaultAttrs'] ?? []),
                    rules: (array) ($entry['rules'] ?? []),
                    description: (string) ($entry['description'] ?? ''),
                    isContainer: (bool) ($entry['isContainer'] ?? false),
                ));
            }

            return $registry;
        });
    }
}
