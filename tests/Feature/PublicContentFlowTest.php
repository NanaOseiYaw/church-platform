<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → database → public website data-flow contract.
 *
 * The rule the public site must obey: content is visible to anonymous visitors
 * if and only if it is (a) published, (b) with a publish time that has already
 * passed, (c) not expired/cancelled, (d) marked 'public' visibility, and
 * (e) owned by the church being served. Every other state must 404 or be absent
 * — including when the URL is guessed directly rather than followed from a list.
 */
class PublicContentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);
        $this->admin  = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');

        // Guests resolve the first church via ResolveTenant's single-tenant fallback.
        app()->instance('church', $this->church);
        app()->instance('church.id', $this->church->id);
    }

    private function makeAnnouncement(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'church_id'    => $this->church->id,
            'created_by'   => $this->admin->id,
            'title'        => 'Sunday Service Announcement',
            'body'         => 'Join us this Sunday at 10:00.',
            'visibility'   => 'public',
            'published_at' => now()->subHour(),
        ], $overrides));
    }

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'church_id'    => $this->church->id,
            'created_by'   => $this->admin->id,
            'title'        => 'Youth Conference',
            'description'  => 'A weekend for our young people.',
            'start_at'     => now()->addWeek(),
            'visibility'   => 'public',
            'is_cancelled' => false,
            'published_at' => now()->subHour(),
        ], $overrides));
    }

    private function announcementTitles(): array
    {
        return collect($this->get('/announcements')
            ->viewData('page')['props']['announcements'])
            ->pluck('title')->all();
    }

    private function eventTitles(): array
    {
        return collect($this->get('/events')
            ->viewData('page')['props']['events'])
            ->pluck('title')->all();
    }

    // ── Announcements ─────────────────────────────────────────────────────────

    public function test_published_public_announcement_appears_on_public_site(): void
    {
        $this->makeAnnouncement();
        $this->assertContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_draft_announcement_is_hidden(): void
    {
        $this->makeAnnouncement(['published_at' => null]);
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_future_scheduled_announcement_is_hidden_until_its_time(): void
    {
        $this->makeAnnouncement(['published_at' => now()->addWeek()]);
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_expired_announcement_is_hidden(): void
    {
        $this->makeAnnouncement(['expires_at' => now()->subMinute()]);
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_members_only_announcement_is_hidden_from_public(): void
    {
        $this->makeAnnouncement(['visibility' => 'members_only']);
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_unpublishing_removes_announcement_from_public_site(): void
    {
        $a = $this->makeAnnouncement();
        $this->assertContains('Sunday Service Announcement', $this->announcementTitles());

        $a->update(['published_at' => null]);
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    public function test_deleting_removes_announcement_from_public_site(): void
    {
        $a = $this->makeAnnouncement();
        $a->delete();
        $this->assertNotContains('Sunday Service Announcement', $this->announcementTitles());
    }

    // ── Events (listing) ──────────────────────────────────────────────────────

    public function test_published_public_event_appears_on_public_site(): void
    {
        $this->makeEvent();
        $this->assertContains('Youth Conference', $this->eventTitles());
    }

    public function test_draft_event_is_hidden(): void
    {
        $this->makeEvent(['published_at' => null]);
        $this->assertNotContains('Youth Conference', $this->eventTitles());
    }

    public function test_cancelled_event_is_hidden(): void
    {
        $this->makeEvent(['is_cancelled' => true]);
        $this->assertNotContains('Youth Conference', $this->eventTitles());
    }

    public function test_members_only_event_is_hidden_from_listing(): void
    {
        $this->makeEvent(['visibility' => 'members_only']);
        $this->assertNotContains('Youth Conference', $this->eventTitles());
    }

    public function test_past_event_is_hidden_from_upcoming_listing(): void
    {
        $this->makeEvent(['start_at' => now()->subWeek()]);
        $this->assertNotContains('Youth Conference', $this->eventTitles());
    }

    // ── Events (direct URL access — the guessed-URL cases) ────────────────────

    public function test_public_event_detail_page_is_reachable(): void
    {
        $event = $this->makeEvent();
        $this->get("/events/{$event->id}")->assertOk();
    }

    public function test_draft_event_detail_page_is_not_reachable(): void
    {
        $event = $this->makeEvent(['published_at' => null]);
        $this->get("/events/{$event->id}")->assertNotFound();
    }

    public function test_members_only_event_detail_page_is_not_reachable_by_guests(): void
    {
        $event = $this->makeEvent(['visibility' => 'members_only']);
        $this->get("/events/{$event->id}")->assertNotFound();
    }

    public function test_future_scheduled_event_detail_page_is_not_reachable_early(): void
    {
        $event = $this->makeEvent(['published_at' => now()->addWeek()]);
        $this->get("/events/{$event->id}")->assertNotFound();
    }

    public function test_cancelled_event_detail_page_is_not_reachable(): void
    {
        $event = $this->makeEvent(['is_cancelled' => true]);
        $this->get("/events/{$event->id}")->assertNotFound();
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_another_churchs_content_never_leaks_onto_this_public_site(): void
    {
        $other      = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['church_id' => $other->id]);

        $foreignEvent = Event::withoutGlobalScope('church')->create([
            'church_id' => $other->id, 'created_by' => $otherAdmin->id,
            'title' => 'Foreign Event', 'start_at' => now()->addWeek(),
            'visibility' => 'public', 'is_cancelled' => false,
            'published_at' => now()->subHour(),
        ]);
        Announcement::withoutGlobalScope('church')->create([
            'church_id' => $other->id, 'created_by' => $otherAdmin->id,
            'title' => 'Foreign Announcement', 'body' => 'Body',
            'visibility' => 'public', 'published_at' => now()->subHour(),
        ]);

        $this->assertNotContains('Foreign Event', $this->eventTitles());
        $this->assertNotContains('Foreign Announcement', $this->announcementTitles());

        // And not reachable by direct URL either.
        $this->get("/events/{$foreignEvent->id}")->assertNotFound();
    }
}
