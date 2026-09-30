<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use App\Support\PageHeroes;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Header image resolution: per-page image → site-wide default → gradient.
 *
 * The homepage deliberately keeps its original `homepage.hero_image` settings
 * key rather than moving into the per-page map, so that existing data keeps
 * working; these tests pin that both locations stay wired up.
 */
class PageHeroImageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $this->church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);
        app()->instance('church', $this->church);
        app()->instance('church.id', $this->church->id);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);
        $user->assignRole('church_admin');
        $this->actingAs($user);

        return $user;
    }

    private function refreshTenant(): Church
    {
        $fresh = $this->church->fresh();
        app()->instance('church', $fresh);

        return $fresh;
    }

    private function image(string $name = 'hero.jpg'): UploadedFile
    {
        // Built from raw bytes rather than UploadedFile::fake()->image()
        // so the suite does not require ext-gd.
        $path = tempnam(sys_get_temp_dir(), 'hero');
        file_put_contents($path, "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xff\xd9");

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    // ── Defaults ──────────────────────────────────────────────────────────────

    public function test_no_images_configured_means_gradient(): void
    {
        $this->assertNull(PageHeroes::default($this->church));
        $this->assertSame([], PageHeroes::map($this->church));
    }

    public function test_shared_props_expose_the_resolution_inputs(): void
    {
        $props = $this->get('/about')->viewData('page')['props'];

        $this->assertArrayHasKey('pageHeroImage', $props);
        $this->assertArrayHasKey('pageHeroImages', $props);
        $this->assertArrayHasKey('pageHeroPaths', $props);

        // Paths map URL → page key, which is how PageHero identifies itself.
        $this->assertSame('about', $props['pageHeroPaths']['/about']);
        $this->assertSame('events', $props['pageHeroPaths']['/events']);
        $this->assertSame('home', $props['pageHeroPaths']['/']);
    }

    // ── Uploading ─────────────────────────────────────────────────────────────

    public function test_admin_can_upload_a_site_wide_default(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(),
        ])->assertRedirect();

        $this->assertNotNull(PageHeroes::default($this->refreshTenant()));
    }

    public function test_admin_can_upload_an_image_for_a_single_page(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(),
            'page'            => 'events',
        ])->assertRedirect();

        $map = PageHeroes::map($this->refreshTenant());

        $this->assertArrayHasKey('events', $map);
        $this->assertArrayNotHasKey('sermons', $map, 'Uploading for one page must not affect another.');
    }

    public function test_setting_one_page_does_not_clear_another(): void
    {
        $this->actingAsAdmin();

        foreach (['events', 'sermons'] as $page) {
            $this->post('/dashboard/settings/website/page-hero-image', [
                'page_hero_image' => $this->image(),
                'page'            => $page,
            ])->assertRedirect();
            $this->refreshTenant();
        }

        $map = PageHeroes::map($this->refreshTenant());

        $this->assertArrayHasKey('events', $map);
        $this->assertArrayHasKey('sermons', $map);
    }

    public function test_homepage_image_is_written_to_its_original_settings_key(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(),
            'page'            => PageHeroes::HOMEPAGE_KEY,
        ])->assertRedirect();

        $fresh = $this->refreshTenant();

        // Stored where the homepage hero has always read from…
        $this->assertNotEmpty($fresh->settings['homepage']['hero_image']);
        // …and surfaced through the unified map.
        $this->assertArrayHasKey('home', PageHeroes::map($fresh));
    }

    public function test_an_unknown_page_key_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(),
            'page'            => 'not-a-real-page',
        ])->assertSessionHasErrors('page');
    }

    // ── Removing ──────────────────────────────────────────────────────────────

    public function test_removing_a_page_image_falls_back_to_the_default(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $this->image()]);
        $this->refreshTenant();
        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(), 'page' => 'events',
        ]);
        $this->refreshTenant();

        $this->delete('/dashboard/settings/website/page-hero-image', ['page' => 'events'])->assertRedirect();

        $fresh = $this->refreshTenant();

        $this->assertArrayNotHasKey('events', PageHeroes::map($fresh));
        $this->assertNotNull(PageHeroes::default($fresh), 'The site-wide default must survive removing a page image.');
    }

    public function test_removing_the_default_returns_pages_to_the_gradient(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $this->image()]);
        $this->refreshTenant();

        $this->delete('/dashboard/settings/website/page-hero-image')->assertRedirect();

        $this->assertNull(PageHeroes::default($this->refreshTenant()));
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_member_cannot_change_header_images(): void
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);
        $user->assignRole('member');
        $this->actingAs($user);

        $this->post('/dashboard/settings/website/page-hero-image', [
            'page_hero_image' => $this->image(),
        ])->assertForbidden();

        $this->delete('/dashboard/settings/website/page-hero-image')->assertForbidden();
    }
}
