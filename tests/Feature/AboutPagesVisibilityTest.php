<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * About sub-page visibility gate.
 *
 * Leadership and History ship as templates. Until an admin fills in the local
 * content they must be invisible to the public — absent from the shared
 * `aboutPages` list that drives the navbar dropdown and the About sub-nav, and
 * 404 on direct access. Admins who can edit the church may still preview them.
 *
 * Beliefs and Core Values come from config/cop.php and are always public.
 */
class AboutPagesVisibilityTest extends TestCase
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

    private function sharedAboutPages(): array
    {
        return $this->get('/about')->viewData('page')['props']['aboutPages'] ?? [];
    }

    // ── Always-public pages ───────────────────────────────────────────────────

    public function test_beliefs_and_core_values_are_always_public(): void
    {
        $this->get('/about/beliefs')->assertOk();
        $this->get('/about/core-values')->assertOk();

        $this->assertEqualsCanonicalizing(['beliefs', 'core-values'], $this->sharedAboutPages());
    }

    // ── Gated pages, empty ────────────────────────────────────────────────────

    public function test_template_pages_are_not_advertised_to_the_public(): void
    {
        $pages = $this->sharedAboutPages();

        $this->assertNotContains('leadership', $pages);
        $this->assertNotContains('history', $pages);
    }

    public function test_guest_gets_404_on_template_pages(): void
    {
        $this->get('/about/leadership')->assertNotFound();
        $this->get('/about/history')->assertNotFound();
    }

    public function test_ordinary_member_also_gets_404_on_template_pages(): void
    {
        $this->actingAsRole('member');

        $this->get('/about/leadership')->assertNotFound();
        $this->get('/about/history')->assertNotFound();
    }

    public function test_admin_can_preview_template_pages(): void
    {
        $this->actingAsRole('church_admin');

        $this->get('/about/leadership')->assertOk();
        $this->get('/about/history')->assertOk();
    }

    // ── Gated pages, filled in ────────────────────────────────────────────────

    public function test_leadership_becomes_public_once_content_is_added(): void
    {
        $this->church->update([
            'settings' => ['about' => ['leadership' => [
                ['name' => 'Kofi Mensah', 'role' => 'Presiding Elder'],
            ]]],
        ]);
        app()->instance('church', $this->church->fresh());

        $this->get('/about/leadership')->assertOk();
        $this->assertContains('leadership', $this->sharedAboutPages());

        // History was not filled in, so it stays hidden.
        $this->get('/about/history')->assertNotFound();
        $this->assertNotContains('history', $this->sharedAboutPages());
    }

    public function test_history_becomes_public_once_content_is_added(): void
    {
        $this->church->update([
            'settings' => ['about' => ['history' => [
                ['year' => '2004', 'title' => 'The assembly is founded', 'body' => 'How it began.'],
            ]]],
        ]);
        app()->instance('church', $this->church->fresh());

        $this->get('/about/history')->assertOk();
        $this->assertContains('history', $this->sharedAboutPages());
    }
}
