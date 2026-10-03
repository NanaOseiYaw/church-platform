<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Optional cover image or flyer on events and announcements.
 *
 * Events already had a cover_image column and a public page that showed it,
 * but nothing could set it; announcements had nothing. Both now upload through
 * App\Support\CoverImage, which these tests drive through the real dashboard
 * routes — including that the uploaded file never reaches the mass-assigned
 * save as a temporary path.
 */
class CoverImageTest extends TestCase
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

        $admin = User::factory()->create(['church_id' => $this->church->id]);
        $admin->assignRole('church_admin');
        $this->actingAs($admin);
    }

    /** A real 1x1 PNG over real bytes, so content checks run as in production. */
    private function png(string $name = 'flyer.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function eventFields(array $extra = []): array
    {
        return array_merge([
            'title'        => 'Youth Week Climax',
            'start_at'     => '2026-11-01 10:00:00',
            'visibility'   => 'public',
            'published_at' => 'now',
        ], $extra);
    }

    private function announcementFields(array $extra = []): array
    {
        return array_merge([
            'title'        => 'Harvest Thanksgiving',
            'body'         => 'Join us on Sunday.',
            'priority'     => 'medium',
            'visibility'   => 'public',
            'published_at' => 'now',
        ], $extra);
    }

    private function diskPath(string $url): string
    {
        return ltrim(substr(parse_url($url, PHP_URL_PATH), strlen('/storage')), '/');
    }

    private function onlyEvent(): Event
    {
        return Event::withoutGlobalScopes()->firstOrFail();
    }

    private function onlyAnnouncement(): Announcement
    {
        return Announcement::withoutGlobalScopes()->firstOrFail();
    }

    // ── Events ───────────────────────────────────────────────────────────────

    public function test_an_event_can_be_created_with_a_flyer(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png()]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $url = $this->onlyEvent()->cover_image;
        $this->assertStringStartsWith('/storage/event-images/', parse_url($url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($this->diskPath($url));
    }

    public function test_an_event_can_be_created_without_one(): void
    {
        $this->post('/dashboard/events', $this->eventFields())->assertSessionHasNoErrors();

        $this->assertNull($this->onlyEvent()->cover_image);
    }

    public function test_the_uploaded_file_never_reaches_the_column_as_a_temp_path(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png()]));

        // The regression this guards: cover_image is fillable, so a file left
        // in validated() would be mass-assigned as something like /tmp/phpA1B2.
        $this->assertStringNotContainsString('tmp', strtolower($this->onlyEvent()->cover_image));
        $this->assertStringEndsWith('.png', $this->onlyEvent()->cover_image);
    }

    public function test_editing_an_event_without_touching_the_image_keeps_it(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png()]));
        $event = $this->onlyEvent();
        $url   = $event->cover_image;

        $this->put("/dashboard/events/{$event->id}", $this->eventFields(['title' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame($url, $event->fresh()->cover_image);
        $this->assertSame('Renamed', $event->fresh()->title);
    }

    public function test_replacing_an_events_image_deletes_the_old_file(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png('a.png')]));
        $event = $this->onlyEvent();
        $old   = $this->diskPath($event->cover_image);

        // Edit forms with a file submit as POST with _method=put.
        $this->post("/dashboard/events/{$event->id}", $this->eventFields([
            '_method'     => 'put',
            'cover_image' => $this->png('b.png'),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($this->diskPath($event->fresh()->cover_image));
    }

    public function test_removing_an_events_image_clears_it_and_deletes_the_file(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png()]));
        $event = $this->onlyEvent();
        $old   = $this->diskPath($event->cover_image);

        $this->put("/dashboard/events/{$event->id}", $this->eventFields(['remove_cover_image' => true]))
            ->assertSessionHasNoErrors();

        $this->assertNull($event->fresh()->cover_image);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_the_public_event_page_receives_the_image(): void
    {
        $this->post('/dashboard/events', $this->eventFields(['cover_image' => $this->png()]));
        $event = $this->onlyEvent();

        auth()->logout();
        $props = $this->get(route('events.show', $event))->assertOk()->viewData('page')['props'];

        $this->assertSame($event->cover_image, $props['event']['image']);
    }

    // ── Announcements ────────────────────────────────────────────────────────

    public function test_an_announcement_can_be_created_with_a_flyer(): void
    {
        $this->post('/dashboard/announcements', $this->announcementFields(['cover_image' => $this->png()]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $url = $this->onlyAnnouncement()->cover_image;
        $this->assertStringStartsWith('/storage/announcement-images/', parse_url($url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($this->diskPath($url));
    }

    public function test_replacing_and_removing_an_announcements_image_cleans_up(): void
    {
        $this->post('/dashboard/announcements', $this->announcementFields(['cover_image' => $this->png('a.png')]));
        $ann   = $this->onlyAnnouncement();
        $first = $this->diskPath($ann->cover_image);

        $this->post("/dashboard/announcements/{$ann->id}", $this->announcementFields([
            '_method' => 'put', 'cover_image' => $this->png('b.png'),
        ]))->assertSessionHasNoErrors();
        $second = $this->diskPath($ann->fresh()->cover_image);
        Storage::disk('public')->assertMissing($first);

        $this->put("/dashboard/announcements/{$ann->id}", $this->announcementFields(['remove_cover_image' => true]));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($ann->fresh()->cover_image);
    }

    public function test_the_public_announcements_page_receives_the_image(): void
    {
        $this->post('/dashboard/announcements', $this->announcementFields(['cover_image' => $this->png()]));
        $url = $this->onlyAnnouncement()->cover_image;

        auth()->logout();
        $props = $this->get('/announcements')->assertOk()->viewData('page')['props'];
        $images = collect($props['announcements']['data'] ?? $props['announcements'])->pluck('image');

        $this->assertContains($url, $images);
    }

    // ── Rejected uploads (same invariant as every upload) ────────────────────

    public function test_unsafe_or_oversized_files_are_rejected(): void
    {
        $cases = [
            'svg (scripts can run)'   => $this->upload('flyer.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'html renamed to .png'    => $this->upload('flyer.png', '<html><script>alert(1)</script></html>'),
            'real png named .html'    => $this->png('flyer.html'),
            'over 10 MB'              => $this->upload('big.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==') . str_repeat("\0", 10 * 1024 * 1024 + 1)),
        ];

        foreach ($cases as $label => $file) {
            $this->post('/dashboard/events', $this->eventFields(['cover_image' => $file]))
                ->assertSessionHasErrors('cover_image');
        }

        $this->assertSame(0, Event::withoutGlobalScopes()->count(), 'no event should be saved with a rejected image');
    }
}
