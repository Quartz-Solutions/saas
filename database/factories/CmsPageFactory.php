<?php

namespace Database\Factories;

use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        $markdown = "# {$title}\n\n".fake()->paragraphs(3, true);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'locale' => 'en',
            'body_markdown' => $markdown,
            'body_html' => '<h1>'.e($title).'</h1><p>'.e(fake()->paragraph()).'</p>',
            'status' => CmsPage::STATUS_PUBLISHED,
            'template' => CmsPage::TEMPLATE_DOCS,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
            'og_image_path' => null,
            'no_index' => false,
            'published_at' => now()->subDay(),
            'author_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => CmsPage::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function legal(): static
    {
        return $this->state(fn () => [
            'template' => CmsPage::TEMPLATE_LEGAL,
        ]);
    }
}
