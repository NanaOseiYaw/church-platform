<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\SermonSeries;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating and managing sermon series from the dashboard.
 *
 * Written after a report that a series could not be made. The page and its
 * routes existed, but nothing in the dashboard linked to them, so the series
 * dropdown on the sermon form stayed empty with no way to fill it.
 */
class SermonSeriesManagementTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

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

    public function test_admin_can_open_the_series_page(): void
    {
        $this->actingAsRole('church_admin');

        $this->get('/dashboard/sermons/series')->assertOk();
    }

    public function test_admin_can_create_a_series(): void
    {
        $this->actingAsRole('church_admin');

        $this->post('/dashboard/sermons/series', [
            'title'       => 'Possessing the Nations',
            'description' => 'A teaching series on Vision 2028.',
            'started_at'  => '2026-09-01',
            'is_active'   => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $series = SermonSeries::where('church_id', $this->church->id)->first();
        $this->assertNotNull($series);
        $this->assertSame('Possessing the Nations', $series->title);
        $this->assertNotEmpty($series->slug, 'slug should be generated from the title');
    }

    public function test_a_new_series_is_offered_on_the_sermon_form(): void
    {
        $this->actingAsRole('church_admin');

        $this->post('/dashboard/sermons/series', ['title' => 'Faith Foundations', 'is_active' => true]);

        $props = $this->get('/dashboard/sermons/create')->viewData('page')['props'];
        $titles = collect($props['seriesList'])->pluck('title');

        $this->assertContains('Faith Foundations', $titles);
    }

    public function test_the_sermons_dashboard_links_to_series_management(): void
    {
        $this->actingAsRole('church_admin');

        // The link lives in the page component, so assert against its source:
        // the regression was that no route to the series page existed in the UI.
        $index = file_get_contents(resource_path('js/Pages/Dashboard/Sermons/Index.vue'));
        $this->assertStringContainsString('/dashboard/sermons/series', $index);
    }

    public function test_a_title_is_required(): void
    {
        $this->actingAsRole('church_admin');

        $this->post('/dashboard/sermons/series', ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_a_member_cannot_create_a_series(): void
    {
        $this->actingAsRole('member');

        $this->post('/dashboard/sermons/series', ['title' => 'Nope'])->assertForbidden();
        $this->assertSame(0, SermonSeries::count());
    }
}
