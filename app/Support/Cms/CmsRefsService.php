<?php

namespace App\Support\Cms;

use App\Models\CmsFaq;
use App\Models\CmsFeature;
use App\Models\CmsLogo;
use App\Models\CmsTestimonial;
use App\Models\Plan;

/**
 * Resolves block references (feature_ids, testimonial_ids, plan_slugs,
 * group_slug for logos and faqs) into a single payload shared via
 * Inertia so the public block renderer can render without N+1 queries.
 *
 * `forBlocks($blocks)` returns a refs bundle scoped to what's actually
 * referenced — never load the full collections on every page.
 */
class CmsRefsService
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, array<int|string, mixed>>
     */
    public function forBlocks(array $blocks): array
    {
        $featureIds = [];
        $testimonialIds = [];
        $logoGroups = [];
        $faqGroups = [];
        $planSlugs = [];

        $this->scan($blocks, $featureIds, $testimonialIds, $logoGroups, $faqGroups, $planSlugs);

        $features = $featureIds === []
            ? []
            : CmsFeature::query()
                ->whereIn('id', array_values(array_unique($featureIds)))
                ->where('is_active', true)
                ->get(['id', 'title', 'description', 'icon'])
                ->keyBy('id')
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'description' => $f->description,
                    'icon' => $f->icon,
                ])
                ->toArray();

        $testimonials = $testimonialIds === []
            ? []
            : CmsTestimonial::query()
                ->whereIn('id', array_values(array_unique($testimonialIds)))
                ->where('is_active', true)
                ->get()
                ->keyBy('id')
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'quote' => $t->quote,
                    'author_name' => $t->author_name,
                    'author_role' => $t->author_role,
                    'company' => $t->company,
                    'avatar_url' => $t->avatar_url,
                    'rating' => $t->rating,
                ])
                ->toArray();

        $logos = [];
        foreach (array_unique($logoGroups) as $group) {
            $logos[$group] = CmsLogo::query()
                ->where('group_slug', $group)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'image_url' => $l->image_url,
                    'url' => $l->url,
                ])
                ->all();
        }

        $faqs = [];
        foreach (array_unique($faqGroups) as $group) {
            $faqs[$group] = CmsFaq::query()
                ->where('group_slug', $group)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'question' => $f->question,
                    'answer_html' => $f->answer_html ?? '',
                ])
                ->all();
        }

        $plans = $planSlugs === []
            ? []
            : Plan::query()
                ->whereIn('slug', array_values(array_unique($planSlugs)))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('price_cents')
                ->get()
                ->keyBy('slug')
                ->map(fn (Plan $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'description' => $p->description ?? '',
                    'price_cents' => (int) $p->price_cents,
                    'currency' => $p->currency,
                    'interval' => $p->billing_period,
                    'features' => array_map(fn ($f) => $f['name'], $p->featuresWithMetadata()),
                    'cta' => (int) $p->price_cents === 0
                        ? 'Start free'
                        : ($p->trial_days > 0 ? "Start {$p->trial_days}-day trial" : 'Choose plan'),
                ])
                ->toArray();

        return [
            'features' => $features,
            'testimonials' => $testimonials,
            'logos' => $logos,
            'faqs' => $faqs,
            'plans' => $plans,
        ];
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<int, int>  $featureIds
     * @param  array<int, int>  $testimonialIds
     * @param  array<int, string>  $logoGroups
     * @param  array<int, string>  $faqGroups
     * @param  array<int, string>  $planSlugs
     */
    private function scan(
        array $blocks,
        array &$featureIds,
        array &$testimonialIds,
        array &$logoGroups,
        array &$faqGroups,
        array &$planSlugs,
    ): void {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if ($type === 'feature_grid' && isset($attrs['feature_ids']) && is_array($attrs['feature_ids'])) {
                foreach ($attrs['feature_ids'] as $id) {
                    $featureIds[] = (int) $id;
                }
            }

            if ($type === 'testimonials' && isset($attrs['testimonial_ids']) && is_array($attrs['testimonial_ids'])) {
                foreach ($attrs['testimonial_ids'] as $id) {
                    $testimonialIds[] = (int) $id;
                }
            }

            if ($type === 'logo_cloud' && isset($attrs['group_slug'])) {
                $logoGroups[] = (string) $attrs['group_slug'];
            }

            if ($type === 'faq' && isset($attrs['group_slug'])) {
                $faqGroups[] = (string) $attrs['group_slug'];
            }

            if ($type === 'pricing' && isset($attrs['plan_slugs']) && is_array($attrs['plan_slugs'])) {
                foreach ($attrs['plan_slugs'] as $slug) {
                    $planSlugs[] = (string) $slug;
                }
            }

            if (isset($block['children']) && is_array($block['children'])) {
                $this->scan($block['children'], $featureIds, $testimonialIds, $logoGroups, $faqGroups, $planSlugs);
            }
        }
    }
}
