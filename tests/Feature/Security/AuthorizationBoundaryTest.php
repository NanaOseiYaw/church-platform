<?php

namespace Tests\Feature\Security;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role and tenant boundary enforcement at the HTTP layer.
 *
 * The dashboard route group is only `['auth']` — every finer-grained check is
 * made inside controllers via policies and authorize(). That design is fine,
 * but it means a single forgotten authorize() call silently opens a hole, and
 * nothing was covering it. These tests pin the boundaries so a regression
 * fails the build rather than reaching production.
 */
class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);
    }

    private function actingAsRole(string $role, ?Church $church = null): User
    {
        $church ??= $this->church;
        $user = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole($role);

        app()->instance('church', $church);
        app()->instance('church.id', $church->id);
        $this->actingAs($user);

        return $user;
    }

    // ── Guests ────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    /** @return array<string, array{string}> */
    public static function protectedDashboardRoutes(): array
    {
        return [
            'dashboard home' => ['/dashboard'],
            'members'        => ['/dashboard/members'],
            'settings'       => ['/dashboard/settings'],
            'reports'        => ['/dashboard/reports'],
            'audit log'      => ['/dashboard/audit'],
            'media library'  => ['/dashboard/media'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('protectedDashboardRoutes')]
    public function test_guest_cannot_reach_protected_route(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    // ── Member (lowest privileged role) ───────────────────────────────────────

    public function test_member_cannot_open_church_settings(): void
    {
        $this->actingAsRole('member');
        $this->get('/dashboard/settings')->assertForbidden();
    }

    public function test_member_cannot_open_reports(): void
    {
        $this->actingAsRole('member');
        $this->get('/dashboard/reports')->assertForbidden();
    }

    public function test_member_cannot_open_audit_log(): void
    {
        $this->actingAsRole('member');
        $this->get('/dashboard/audit')->assertForbidden();
    }

    public function test_member_cannot_create_a_member(): void
    {
        $this->actingAsRole('member');
        $this->post('/dashboard/members', [
            'name' => 'Intruder', 'email' => 'intruder@example.com', 'role' => 'church_admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_member_cannot_update_church_settings(): void
    {
        $this->actingAsRole('member');
        $this->put('/dashboard/settings', ['name' => 'Hijacked Church'])->assertForbidden();
        $this->assertSame('COP Amsterdam', $this->church->fresh()->name);
    }

    public function test_member_cannot_reach_super_admin_panel(): void
    {
        $this->actingAsRole('member');
        $this->get('/super-admin')->assertForbidden();
    }

    public function test_church_admin_cannot_reach_super_admin_panel(): void
    {
        $this->actingAsRole('church_admin');
        $this->get('/super-admin')->assertForbidden();
    }

    // ── Privilege escalation via self-service role change ─────────────────────

    public function test_member_cannot_escalate_own_role_through_profile_update(): void
    {
        $user = $this->actingAsRole('member');

        $this->patch('/dashboard/profile', [
            'name'  => 'Still A Member',
            'role'  => 'church_admin',
            'roles' => ['church_admin'],
        ]);

        $this->assertTrue($user->fresh()->hasRole('member'));
        $this->assertFalse($user->fresh()->hasRole('church_admin'), 'Member escalated to church_admin via profile update.');
    }

    public function test_member_cannot_reassign_own_church_through_profile_update(): void
    {
        $other = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $user  = $this->actingAsRole('member');

        $this->patch('/dashboard/profile', [
            'name'      => 'Wanderer',
            'church_id' => $other->id,
        ]);

        $this->assertSame($this->church->id, $user->fresh()->church_id, 'User moved themselves to another tenant.');
    }

    // ── Cross-tenant IDOR ─────────────────────────────────────────────────────

    public function test_admin_cannot_read_another_churchs_event(): void
    {
        $other      = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['church_id' => $other->id]);

        $foreign = Event::withoutGlobalScope('church')->create([
            'church_id' => $other->id, 'created_by' => $otherAdmin->id,
            'title' => 'Foreign Event', 'start_at' => now()->addWeek(),
            'visibility' => 'public', 'published_at' => now()->subHour(),
        ]);

        $this->actingAsRole('church_admin');
        $this->get("/dashboard/events/{$foreign->id}")->assertNotFound();
    }

    public function test_admin_cannot_read_another_churchs_announcement(): void
    {
        $other      = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['church_id' => $other->id]);

        $foreign = Announcement::withoutGlobalScope('church')->create([
            'church_id' => $other->id, 'created_by' => $otherAdmin->id,
            'title' => 'Foreign Announcement', 'body' => 'Secret',
            'visibility' => 'public', 'published_at' => now()->subHour(),
        ]);

        $this->actingAsRole('church_admin');
        $this->get("/dashboard/announcements/{$foreign->id}")->assertNotFound();
    }

    public function test_admin_cannot_delete_another_churchs_event(): void
    {
        $other      = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['church_id' => $other->id]);

        $foreign = Event::withoutGlobalScope('church')->create([
            'church_id' => $other->id, 'created_by' => $otherAdmin->id,
            'title' => 'Foreign Event', 'start_at' => now()->addWeek(),
            'visibility' => 'public', 'published_at' => now()->subHour(),
        ]);

        $this->actingAsRole('church_admin');
        $this->delete("/dashboard/events/{$foreign->id}")->assertNotFound();

        $this->assertNotNull(
            Event::withoutGlobalScope('church')->find($foreign->id),
            'Another tenant\'s event was deleted.'
        );
    }

    // ── Deactivated accounts ──────────────────────────────────────────────────

    public function test_deactivated_user_cannot_use_the_dashboard(): void
    {
        $user = User::factory()->create(['church_id' => $this->church->id, 'is_active' => false]);
        $user->assignRole('member');

        app()->instance('church', $this->church);
        app()->instance('church.id', $this->church->id);

        $response = $this->actingAs($user)->get('/dashboard');
        $this->assertContains($response->status(), [302, 403], 'Deactivated user reached the dashboard.');
    }
}
