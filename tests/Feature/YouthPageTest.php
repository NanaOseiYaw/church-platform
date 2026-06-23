<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YouthPageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        // A single church is enough: ResolveTenant falls back to the first
        // church for guests on public routes.
        $this->church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-' . uniqid(),
        ]);
    }

    public function test_youth_page_renders_with_upcoming_events_and_leader(): void
    {
        $leader = User::factory()->create([
            'church_id' => $this->church->id,
            'name'      => 'Pastor Mensah',
        ]);

        Department::create([
            'church_id'      => $this->church->id,
            'name'           => 'Youth Ministry',
            'slug'           => 'youth-ministry',
            'is_active'      => true,
            'coordinator_id' => $leader->id,
        ]);

        // A published, public, upcoming event — must surface in the feed.
        Event::create([
            'church_id'    => $this->church->id,
            'created_by'   => $leader->id,
            'title'        => 'Youth Hangout',
            'description'  => 'A fun night of worship and friends.',
            'location'     => 'Main Hall',
            'category'     => 'Youth',
            'start_at'     => now()->addDays(3),
            'end_at'       => now()->addDays(3)->addHours(2),
            'all_day'      => false,
            'visibility'   => 'public',
            'is_public'    => true,
            'is_cancelled' => false,
            'published_at' => now()->subDay(),
            'is_featured'  => false,
        ]);

        $response = $this->get('/ministries/youth');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Youth')
            ->where('leader', 'Pastor Mensah')
            ->has('events', 1)
            ->where('events.0.title', 'Youth Hangout')
            ->where('events.0.location', 'Main Hall')
        );
    }

    public function test_youth_page_renders_with_empty_events_when_none_exist(): void
    {
        $response = $this->get('/ministries/youth');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Youth')
            ->has('events', 0)
            ->where('leader', null)
        );
    }

    public function test_past_and_unpublished_events_are_excluded(): void
    {
        $author = User::factory()->create(['church_id' => $this->church->id]);

        // Past event — excluded by the upcoming() scope.
        Event::create([
            'church_id'    => $this->church->id,
            'created_by'   => $author->id,
            'title'        => 'Last Year Camp',
            'category'     => 'Youth',
            'start_at'     => now()->subWeek(),
            'end_at'       => now()->subWeek()->addHours(2),
            'all_day'      => false,
            'visibility'   => 'public',
            'is_public'    => true,
            'is_cancelled' => false,
            'published_at' => now()->subMonth(),
        ]);

        // Draft (unpublished) upcoming event — excluded by publiclyVisible().
        Event::create([
            'church_id'    => $this->church->id,
            'created_by'   => $author->id,
            'title'        => 'Secret Draft',
            'category'     => 'Youth',
            'start_at'     => now()->addDays(2),
            'end_at'       => now()->addDays(2)->addHours(2),
            'all_day'      => false,
            'visibility'   => 'public',
            'is_public'    => true,
            'is_cancelled' => false,
            'published_at' => null,
        ]);

        $response = $this->get('/ministries/youth');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Youth')
            ->has('events', 0)
        );
    }
}
