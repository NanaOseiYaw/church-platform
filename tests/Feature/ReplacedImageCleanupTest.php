<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Replacing or removing an uploaded image deletes the file it replaced.
 *
 * Every one of these uploads used to leave the old file on the server's disk
 * for good — found when a 3.55 MB hero image stayed behind after being
 * replaced. They store only the public URL, so the old file has to be found by
 * working back from that URL. These tests also pin the guards on that: a file
 * still referenced elsewhere is kept, and a URL that resolves outside the
 * upload's own folder — or off this site entirely — never causes a delete.
 */
class ReplacedImageCleanupTest extends TestCase
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

    /** A real 1x1 PNG over real bytes, so content checks run as in production (no ext-gd needed). */
    private function png(string $name = 'image.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        return new UploadedFile($path, $name, null, null, true);
    }

    private function settings(string $namespace): array
    {
        return $this->church->fresh()->settings[$namespace] ?? [];
    }

    /** The disk path behind a URL the app stored, e.g. ".../storage/logos/x.png" → "logos/x.png". */
    private function diskPath(string $url): string
    {
        return ltrim(substr(parse_url($url, PHP_URL_PATH), strlen('/storage')), '/');
    }

    /** Upload twice through $upload, and assert the first file is gone and the second kept. */
    private function assertReplacementDeletesOldFile(callable $upload, callable $currentUrl): void
    {
        $upload($this->png('first.png'));
        $first = $this->diskPath($currentUrl());
        Storage::disk('public')->assertExists($first);

        $upload($this->png('second.png'));
        $second = $this->diskPath($currentUrl());

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    // ── Each replace-style upload ────────────────────────────────────────────

    public function test_replacing_the_homepage_hero_image_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/homepage/hero-image', ['hero_image' => $f]),
            fn () => $this->settings('homepage')['hero_image'],
        );
    }

    public function test_replacing_the_homepage_hero_from_page_headers_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        // The "Homepage" entry in page headers writes the same setting.
        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $f, 'page' => 'home']),
            fn () => $this->settings('homepage')['hero_image'],
        );
    }

    public function test_replacing_one_page_header_deletes_only_that_pages_old_image(): void
    {
        $this->actingAsAdmin();

        $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $this->png(), 'page' => 'events']);
        $events = $this->diskPath($this->settings('website')['page_hero_images']['events']);

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $f, 'page' => 'about']),
            fn () => $this->settings('website')['page_hero_images']['about'],
        );

        Storage::disk('public')->assertExists($events);
    }

    public function test_replacing_the_site_wide_header_default_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $f]),
            fn () => $this->settings('website')['page_hero_image'],
        );
    }

    public function test_removing_a_page_header_deletes_its_file(): void
    {
        $this->actingAsAdmin();
        $this->post('/dashboard/settings/website/page-hero-image', ['page_hero_image' => $this->png(), 'page' => 'about']);
        $path = $this->diskPath($this->settings('website')['page_hero_images']['about']);

        $this->delete('/dashboard/settings/website/page-hero-image', ['page' => 'about'])->assertRedirect();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_replacing_the_logo_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/branding/logo', ['logo' => $f]),
            fn () => $this->church->fresh()->logo,
        );
    }

    public function test_replacing_the_favicon_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/branding/favicon', ['favicon' => $f]),
            fn () => $this->settings('branding')['favicon'],
        );
    }

    public function test_replacing_the_social_share_image_deletes_the_old_one(): void
    {
        $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/settings/seo/og-image', ['og_image' => $f]),
            fn () => $this->settings('seo')['og_image'],
        );
    }

    public function test_replacing_an_avatar_deletes_the_old_one(): void
    {
        $user = $this->actingAsAdmin();

        $this->assertReplacementDeletesOldFile(
            fn ($f) => $this->post('/dashboard/profile/avatar', ['avatar' => $f]),
            fn () => $user->fresh()->avatar,
        );
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    public function test_a_file_still_used_elsewhere_is_kept(): void
    {
        $this->actingAsAdmin();

        // Point the About header at the homepage's current image, then replace
        // the homepage image. The file is still in use, so it must survive.
        $this->post('/dashboard/settings/homepage/hero-image', ['hero_image' => $this->png()]);
        $shared = $this->settings('homepage')['hero_image'];
        $settings = $this->church->fresh()->settings;
        $settings['website']['page_hero_images']['about'] = $shared;
        $this->church->update(['settings' => $settings]);

        $this->post('/dashboard/settings/homepage/hero-image', ['hero_image' => $this->png()]);

        Storage::disk('public')->assertExists($this->diskPath($shared));
    }

    public function test_a_url_outside_the_uploads_own_folder_is_never_deleted(): void
    {
        $this->actingAsAdmin();

        // A hero image setting that points into the logos folder, and one that
        // tries to climb out of hero-images, must not reach those files.
        Storage::disk('public')->put('logos/keep.png', 'x');
        Storage::disk('public')->put('keep-root.png', 'x');

        foreach (['/storage/logos/keep.png', '/storage/hero-images/../keep-root.png'] as $url) {
            $this->church->update(['settings' => ['homepage' => ['hero_image' => $url]]]);
            $this->post('/dashboard/settings/homepage/hero-image', ['hero_image' => $this->png()]);
        }

        Storage::disk('public')->assertExists('logos/keep.png');
        Storage::disk('public')->assertExists('keep-root.png');
    }

    public function test_an_image_hosted_elsewhere_is_ignored(): void
    {
        $this->actingAsAdmin();
        Storage::disk('public')->put('hero-images/same-name.png', 'x');

        // Same path, different host: that URL is not one of our files.
        $this->church->update(['settings' => ['homepage' => [
            'hero_image' => 'https://cdn.example.com/storage/hero-images/same-name.png',
        ]]]);
        $this->post('/dashboard/settings/homepage/hero-image', ['hero_image' => $this->png()]);

        Storage::disk('public')->assertExists('hero-images/same-name.png');
    }
}
