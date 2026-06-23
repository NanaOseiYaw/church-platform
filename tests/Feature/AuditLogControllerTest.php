<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $coordinator;
    private User $member;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church      = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin       = User::factory()->create(['church_id' => $this->church->id]);
        $this->coordinator = User::factory()->create(['church_id' => $this->church->id]);
        $this->member      = User::factory()->create(['church_id' => $this->church->id]);

        $this->admin->assignRole('church_admin');
        $this->coordinator->assignRole('coordinator');
        $this->member->assignRole('member');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media Team',
            'slug'      => 'media-team',
            'is_active' => true,
        ]);

        // Bind church context so resolvedChurchId() works in tests
        app()->instance('church.id', $this->church->id);
    }

    private function createLog(string $action, array $metadata = [], ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'church_id'  => $this->church->id,
            'user_id'    => ($actor ?? $this->admin)->id,
            'action'     => $action,
            'metadata'   => $metadata ?: null,
        ]);
    }

    public function test_admin_can_view_audit_log_index(): void
    {
        $this->createLog('auth.login');
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        $response = $this->actingAs($this->admin)->get('/dashboard/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Audit/Index')
                 ->has('logs.data', 2)
        );
    }

    public function test_member_cannot_view_audit_log(): void
    {
        $response = $this->actingAs($this->member)->get('/dashboard/audit');
        $response->assertForbidden();
    }

    public function test_coordinator_sees_only_their_department_events(): void
    {
        // Coordinator is member of dept
        $this->dept->members()->attach($this->coordinator->id, ['role' => 'coordinator', 'joined_at' => now()]);

        // Event with dept metadata — should be visible
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        // Event without dept metadata — should NOT be visible to coordinator
        $this->createLog('auth.login');

        $response = $this->actingAs($this->coordinator)->get('/dashboard/audit');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Audit/Index')
                 ->has('logs.data', 1)  // only dept event
        );
    }

    public function test_admin_can_filter_by_module(): void
    {
        $this->createLog('auth.login');
        $this->createLog('announcement.published', ['department_id' => $this->dept->id]);

        $response = $this->actingAs($this->admin)->get('/dashboard/audit?module=auth');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('logs.data', 1)
        );
    }

    public function test_admin_can_filter_by_date_range(): void
    {
        // Create a log with a past date
        $old = $this->createLog('auth.login');
        $old->update(['created_at' => now()->subDays(10)]);

        // Create a recent log
        $this->createLog('auth.logout');

        $response = $this->actingAs($this->admin)->get('/dashboard/audit?date_from=' . now()->subDay()->format('Y-m-d'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('logs.data', 1)
        );
    }
}
