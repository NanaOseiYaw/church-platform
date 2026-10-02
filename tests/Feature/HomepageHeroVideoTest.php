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
 * Optional background video for the homepage hero.
 *
 * The upload follows the same rule as every other upload on the platform:
 * the file's real content (mimetypes, via finfo) AND its filename (extensions)
 * must both pass. Either check alone can be fooled — see the security
 * invariants. The video is also large enough that replaced and removed files
 * must be deleted rather than left to accumulate on the server's disk.
 */
class HomepageHeroVideoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/dashboard/settings/homepage/hero-video';

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

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * A real UploadedFile over real bytes, NOT UploadedFile::fake().
     *
     * Laravel's fake files report a MIME type derived from the filename, never
     * from the content, so a content check run against one tests nothing: an
     * HTML file named clip.mp4 would be reported as an MP4. A real UploadedFile
     * in test mode is inspected with finfo, exactly as production does. The
     * existing upload security tests use the same approach for the same reason.
     */
    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** Smallest bytes finfo recognises as video/mp4 (an ISO-BMFF `ftyp` box). */
    private function mp4(string $name = 'clip.mp4', int $padKb = 1): UploadedFile
    {
        return $this->upload($name, "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . str_repeat("\x00", $padKb * 1024));
    }

    /** Smallest bytes finfo recognises as video/webm (an EBML header, doctype webm). */
    private function webm(string $name = 'clip.webm'): UploadedFile
    {
        return $this->upload($name, "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08"
            . "\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02" . str_repeat("\x00", 1024));
    }

    private function homepageSettings(): array
    {
        return $this->church->fresh()->settings['homepage'] ?? [];
    }

    // ── Accepted ─────────────────────────────────────────────────────────────

    public function test_admin_can_upload_an_mp4(): void
    {
        $this->actingAsRole('church_admin');

        $this->post(self::URL, ['hero_video' => $this->mp4()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $settings = $this->homepageSettings();
        $this->assertNotEmpty($settings['hero_video']);
        Storage::disk('public')->assertExists($settings['hero_video_path']);
        $this->assertStringEndsWith('.mp4', $settings['hero_video_path']);
    }

    public function test_admin_can_upload_a_webm(): void
    {
        $this->actingAsRole('church_admin');

        $this->post(self::URL, ['hero_video' => $this->webm()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.webm', $this->homepageSettings()['hero_video_path']);
    }

    public function test_uploading_a_video_leaves_the_hero_image_alone(): void
    {
        $this->actingAsRole('church_admin');
        $this->church->update(['settings' => ['homepage' => ['hero_image' => '/storage/hero-images/x.jpg']]]);

        $this->post(self::URL, ['hero_video' => $this->mp4()]);

        // The image is the video's poster and fallback, so it must survive.
        $this->assertSame('/storage/hero-images/x.jpg', $this->homepageSettings()['hero_image']);
    }

    // ── Rejected ─────────────────────────────────────────────────────────────

    public function test_html_renamed_to_mp4_is_rejected_on_content(): void
    {
        $this->actingAsRole('church_admin');
        $fake = $this->upload('clip.mp4', '<html><script>alert(1)</script></html>');

        $this->post(self::URL, ['hero_video' => $fake])->assertSessionHasErrors('hero_video');
        $this->assertArrayNotHasKey('hero_video', $this->homepageSettings());
    }

    public function test_real_video_with_a_hostile_extension_is_rejected_on_name(): void
    {
        $this->actingAsRole('church_admin');

        // Genuine MP4 content, so the content check alone would pass it.
        $this->post(self::URL, ['hero_video' => $this->mp4('clip.html')])
            ->assertSessionHasErrors('hero_video');
        $this->post(self::URL, ['hero_video' => $this->mp4('clip.php')])
            ->assertSessionHasErrors('hero_video');
    }

    public function test_files_over_ten_megabytes_are_rejected(): void
    {
        $this->actingAsRole('church_admin');

        $this->post(self::URL, ['hero_video' => $this->mp4('big.mp4', 10 * 1024 + 1)])
            ->assertSessionHasErrors('hero_video');
    }

    public function test_members_cannot_upload(): void
    {
        $this->actingAsRole('member');

        $this->post(self::URL, ['hero_video' => $this->mp4()])->assertForbidden();
    }

    // ── Cleanup ──────────────────────────────────────────────────────────────

    public function test_replacing_a_video_deletes_the_old_file(): void
    {
        $this->actingAsRole('church_admin');

        $this->post(self::URL, ['hero_video' => $this->mp4('first.mp4')]);
        $first = $this->homepageSettings()['hero_video_path'];

        $this->post(self::URL, ['hero_video' => $this->mp4('second.mp4')]);
        $second = $this->homepageSettings()['hero_video_path'];

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_removing_the_video_deletes_the_file_and_the_setting(): void
    {
        $this->actingAsRole('church_admin');

        $this->post(self::URL, ['hero_video' => $this->mp4()]);
        $path = $this->homepageSettings()['hero_video_path'];

        $this->delete(self::URL)->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->homepageSettings()['hero_video'] ?? null);
    }

    // ── Public homepage ──────────────────────────────────────────────────────

    public function test_homepage_receives_the_video_url(): void
    {
        $this->actingAsRole('church_admin');
        $this->post(self::URL, ['hero_video' => $this->mp4()]);
        $url = $this->homepageSettings()['hero_video'];

        auth()->logout();
        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame($url, $props['heroVideo']);
    }

    public function test_homepage_has_no_video_by_default(): void
    {
        $props = $this->get('/')->viewData('page')['props'];

        $this->assertNull($props['heroVideo']);
    }
}
