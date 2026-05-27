<?php

namespace Tests\Feature\Marketing;

use App\Models\NotFoundLog;
use App\Models\Redirect as RedirectRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_redirect_issues_30x_and_bumps_hits(): void
    {
        RedirectRow::query()->create([
            'from_path' => '/old-marketing-url',
            'to_path' => '/pricing',
            'status_code' => 301,
            'is_active' => true,
            'hits' => 0,
        ]);

        $response = $this->get('/old-marketing-url');

        $response->assertStatus(301);
        $response->assertHeader('Location', url('/pricing'));

        $this->assertSame(1, RedirectRow::query()->first()->hits);
    }

    public function test_inactive_redirect_is_ignored(): void
    {
        RedirectRow::query()->create([
            'from_path' => '/disabled',
            'to_path' => '/somewhere',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->get('/disabled')->assertStatus(404);
    }

    public function test_redirect_to_external_url_keeps_scheme(): void
    {
        RedirectRow::query()->create([
            'from_path' => '/old-blog',
            'to_path' => 'https://example.com/blog',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $this->get('/old-blog')
            ->assertStatus(302)
            ->assertHeader('Location', 'https://example.com/blog');
    }

    public function test_404_is_logged_to_not_found_table(): void
    {
        $this->get('/definitely-not-a-real-page-9999');

        $row = NotFoundLog::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('/definitely-not-a-real-page-9999', $row->path);
        $this->assertSame(1, $row->hits);
    }

    public function test_repeat_404_increments_hits(): void
    {
        $this->get('/missing-page-x');
        $this->get('/missing-page-x');
        $this->get('/missing-page-x');

        $row = NotFoundLog::query()->where('path', '/missing-page-x')->first();
        $this->assertSame(3, $row->hits);
    }

    public function test_admin_can_create_redirect(): void
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/admin/cms/redirects', [
                'from_path' => '/old',
                'to_path' => '/new',
                'status_code' => 301,
            ])
            ->assertRedirect();

        $this->assertSame(1, RedirectRow::query()->count());
    }

    public function test_admin_can_convert_404_to_redirect(): void
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $log = NotFoundLog::query()->create(['path' => '/orphan', 'hits' => 5, 'last_hit_at' => now()]);

        $this->actingAs($admin)
            ->post("/admin/cms/redirects/from-404/{$log->id}", [
                'from_path' => '/orphan',
                'to_path' => '/about',
                'status_code' => 301,
            ])
            ->assertRedirect();

        $this->assertSame(1, RedirectRow::query()->count());
        $row = RedirectRow::query()->first();
        $this->assertSame('/orphan', $row->from_path);
        $this->assertSame('/about', $row->to_path);
    }
}
