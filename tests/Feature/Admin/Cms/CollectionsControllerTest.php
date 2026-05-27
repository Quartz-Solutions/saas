<?php

namespace Tests\Feature\Admin\Cms;

use App\Models\CmsFaq;
use App\Models\CmsFeature;
use App\Models\CmsLogo;
use App\Models\CmsTestimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_unknown_type_404s(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)->get('/admin/cms/collections/nope')->assertStatus(404);
    }

    public function test_features_crud(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/cms/collections/features', [
                'title' => 'Speedy',
                'description' => 'It is fast.',
                'icon' => 'zap',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $feature = CmsFeature::query()->where('title', 'Speedy')->first();
        $this->assertNotNull($feature);

        $this->actingAs($admin)
            ->patch("/admin/cms/collections/features/{$feature->id}", [
                'title' => 'Speedier',
                'description' => 'Even faster.',
                'icon' => 'zap',
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $this->assertSame('Speedier', $feature->fresh()->title);

        $this->actingAs($admin)
            ->delete("/admin/cms/collections/features/{$feature->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($feature);
    }

    public function test_testimonials_create_validates_quote_required(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->postJson('/admin/cms/collections/testimonials', [
                'author_name' => 'Person',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quote']);
    }

    public function test_faqs_default_group_when_absent(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/cms/collections/faqs', [
                'group_slug' => 'pricing',
                'question' => 'How much?',
                'answer_html' => '<p>$10.</p>',
            ])
            ->assertRedirect();

        $faq = CmsFaq::query()->first();
        $this->assertSame('pricing', $faq->group_slug);
    }

    public function test_logos_crud(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/cms/collections/logos', [
                'group_slug' => 'customers',
                'name' => 'Acme',
                'image_url' => 'https://example.test/acme.png',
            ])
            ->assertRedirect();

        $this->assertSame(1, CmsLogo::query()->count());

        $logo = CmsLogo::query()->first();
        $this->actingAs($admin)
            ->delete("/admin/cms/collections/logos/{$logo->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($logo);
    }

    public function test_index_renders_with_items_and_field_schema(): void
    {
        $admin = $this->makeSuperAdmin();

        CmsTestimonial::query()->create([
            'quote' => 'Loved it.',
            'author_name' => 'Pat',
            'rating' => 5,
        ]);

        $this->actingAs($admin)
            ->get('/admin/cms/collections/testimonials')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cms/collections/index')
                ->where('type', 'testimonials')
                ->has('items', 1)
                ->where('items.0.author_name', 'Pat')
                ->has('fields')
            );
    }
}
