<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchSettingsContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── Tenant setup helper ──────────────────────────────────────────────────────

    private function makeChurchAdmin(): array
    {
        $church = Church::create(['name' => 'Test Church ' . uniqid(), 'is_active' => true]);
        $user   = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole('church_admin');

        app()->instance('church',    $church);
        app()->instance('church.id', $church->id);
        $this->actingAs($user);

        return [$church, $user];
    }

    // ── Task 3: Suggested donation amounts ──────────────────────────────────────

    public function test_suggested_amounts_are_saved_to_donations_settings(): void
    {
        [$church] = $this->makeChurchAdmin();

        $this->put('/dashboard/settings/donations', [
            'funds'             => [],
            'suggested_amounts' => [10, 25, 50, 100],
        ])->assertRedirect();

        $church->refresh();
        $this->assertSame([10, 25, 50, 100], $church->settings['donations']['suggested_amounts']);
    }

    public function test_about_hero_copy_is_saved_to_settings(): void
    {
        [$church] = $this->makeChurchAdmin();

        $this->put('/dashboard/settings/about-content', [
            'team'                => [],
            'values'              => [],
            'hero_title'          => 'Grace Church',
            'hero_eyebrow'        => 'Our Journey',
            'hero_subtitle'       => 'A community growing in faith.',
            'leadership_subtitle' => 'Serving with humility.',
        ])->assertRedirect();

        $church->refresh();
        $this->assertSame('Grace Church',              $church->settings['about']['hero_title']);
        $this->assertSame('A community growing in faith.', $church->settings['about']['hero_subtitle']);
        $this->assertSame('Serving with humility.',    $church->settings['about']['leadership_subtitle']);

        // The About hero no longer renders a label above its heading, so the
        // setting that fed one was removed. Still posted above to prove an
        // unknown key is dropped rather than written through to settings.
        $this->assertArrayNotHasKey('hero_eyebrow', $church->settings['about']);
    }

    public function test_suggested_amounts_reject_non_integers(): void
    {
        $this->makeChurchAdmin();

        $this->put('/dashboard/settings/donations', [
            'funds'             => [],
            'suggested_amounts' => ['abc', 'xyz'],
        ])->assertSessionHasErrors(['suggested_amounts.0', 'suggested_amounts.1']);
    }

    public function test_homepage_copy_strings_are_saved(): void
    {
        [$church] = $this->makeChurchAdmin();

        $this->put('/dashboard/settings/homepage', [
            'hero_description'      => null,
            'stats'                 => [],
            'testimonials'          => [],
            'events_subtitle'       => 'Come and join us!',
            'ministry_heading'      => 'Our Community',
            'ministry_body'         => 'A place for everyone.',
            'sermons_subtitle'      => 'Teaching that transforms.',
            'testimonials_subtitle' => 'Stories of grace.',
            'livestream_cta'        => 'Watch from anywhere.',
            'sermons_page_subtitle' => 'Rooted in scripture.',
        ])->assertRedirect();

        $church->refresh();
        $this->assertSame('Come and join us!',         $church->settings['homepage']['events_subtitle']);
        $this->assertSame('Our Community',             $church->settings['homepage']['ministry_heading']);
        $this->assertSame('A place for everyone.',     $church->settings['homepage']['ministry_body']);
        $this->assertSame('Teaching that transforms.', $church->settings['homepage']['sermons_subtitle']);
        $this->assertSame('Stories of grace.',         $church->settings['homepage']['testimonials_subtitle']);
        $this->assertSame('Watch from anywhere.',      $church->settings['homepage']['livestream_cta']);
        $this->assertSame('Rooted in scripture.',      $church->settings['homepage']['sermons_page_subtitle']);
    }

    public function test_section_visibility_is_saved_and_defaults_to_true(): void
    {
        [$church] = $this->makeChurchAdmin();

        // Save with events hidden
        $this->put('/dashboard/settings/homepage', [
            'hero_description' => null,
            'stats'            => [],
            'testimonials'     => [],
            'section_visibility' => [
                'events'        => false,
                'ministry'      => true,
                'sermons'       => true,
                'testimonials'  => true,
                'announcements' => true,
                'livestream'    => true,
            ],
        ])->assertRedirect();

        $church->refresh();
        $saved = $church->settings['homepage']['section_visibility'];

        $this->assertFalse($saved['events']);
        $this->assertTrue($saved['ministry']);
        $this->assertTrue($saved['sermons']);
        $this->assertTrue($saved['testimonials']);
        $this->assertTrue($saved['announcements']);
        $this->assertTrue($saved['livestream']);
    }

    public function test_section_visibility_defaults_all_true_when_omitted(): void
    {
        [$church] = $this->makeChurchAdmin();

        // PUT without section_visibility key at all
        $this->put('/dashboard/settings/homepage', [
            'hero_description' => null,
            'stats'            => [],
            'testimonials'     => [],
        ])->assertRedirect();

        $church->refresh();
        $visibility = $church->settings['homepage']['section_visibility'];

        foreach (['events', 'ministry', 'sermons', 'testimonials', 'announcements', 'livestream'] as $section) {
            $this->assertTrue($visibility[$section], "Section '{$section}' should default to true when omitted");
        }
    }

    // ── Footer nav ───────────────────────────────────────────────────────────────

    public function test_footer_nav_is_saved_to_website_settings(): void
    {
        [$church] = $this->makeChurchAdmin();

        $this->put('/dashboard/settings/website', [
            'domain'       => null,
            'timezone'     => 'UTC',
            'language'     => 'en',
            'privacy_mode' => false,
            'footer_nav'   => [
                'explore_links' => [
                    ['label' => 'About',    'href' => '/about'],
                    ['label' => 'Sermons',  'href' => '/sermons'],
                ],
                'connect_links' => [
                    ['label' => 'Give',     'href' => '/give'],
                ],
            ],
        ])->assertRedirect();

        $church->refresh();
        $nav = $church->settings['website']['footer_nav'];

        $this->assertCount(2, $nav['explore_links']);
        $this->assertSame('About',   $nav['explore_links'][0]['label']);
        $this->assertSame('/about',  $nav['explore_links'][0]['href']);
        $this->assertSame('Sermons', $nav['explore_links'][1]['label']);
        $this->assertSame('/sermons', $nav['explore_links'][1]['href']);
        $this->assertCount(1, $nav['connect_links']);
        $this->assertSame('Give',    $nav['connect_links'][0]['label']);
        $this->assertSame('/give',   $nav['connect_links'][0]['href']);
    }

    public function test_footer_nav_explore_link_label_is_required(): void
    {
        $this->makeChurchAdmin();

        $this->put('/dashboard/settings/website', [
            'domain'       => null,
            'timezone'     => 'UTC',
            'language'     => 'en',
            'privacy_mode' => false,
            'footer_nav'   => [
                'explore_links' => [
                    ['label' => '', 'href' => '/about'],
                ],
                'connect_links' => [],
            ],
        ])->assertSessionHasErrors(['footer_nav.explore_links.0.label']);
    }

    public function test_footer_nav_href_is_required(): void
    {
        $this->makeChurchAdmin();

        $this->put('/dashboard/settings/website', [
            'domain'       => null,
            'timezone'     => 'UTC',
            'language'     => 'en',
            'privacy_mode' => false,
            'footer_nav'   => [
                'explore_links' => [],
                'connect_links' => [
                    ['label' => 'Contact', 'href' => ''],
                ],
            ],
        ])->assertSessionHasErrors(['footer_nav.connect_links.0.href']);
    }

    public function test_website_get_returns_footer_nav_with_defaults(): void
    {
        $this->makeChurchAdmin();

        $response = $this->get('/dashboard/settings/website');
        $response->assertOk();

        // The website GET endpoint should return footer_nav settings in the Inertia props
        // even when nothing has been saved yet (defaults from controller)
        $inertiaProps = $response->viewData('page')['props'] ?? [];
        $this->assertArrayHasKey('settings', $inertiaProps);

        $settings = $inertiaProps['settings'];
        $this->assertArrayHasKey('footer_nav', $settings);
        $this->assertArrayHasKey('explore_links', $settings['footer_nav']);
        $this->assertArrayHasKey('connect_links', $settings['footer_nav']);
        // Should have at least the default footer nav links from the controller
        $this->assertNotEmpty($settings['footer_nav']['explore_links']);
        $this->assertNotEmpty($settings['footer_nav']['connect_links']);
    }
}
