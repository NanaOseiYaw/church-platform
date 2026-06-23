<?php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServicePlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private Department $dept;
    private ServingPosition $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church   = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin    = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media',
            'slug'      => 'media',
            'is_active' => true,
        ]);

        $this->position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);
    }

    private function planData(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Sunday Service',
            'description'  => null,
            'scheduled_at' => '2026-07-06 09:00:00',
            'location'     => 'Main Hall',
            'notes'        => null,
        ], $overrides);
    }

    public function test_admin_can_list_plans(): void
    {
        ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard/scheduling/plans');

        $response->assertOk();
    }

    public function test_admin_can_create_a_plan(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/plans', $this->planData());

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', [
            'church_id' => $this->church->id,
            'title'     => 'Sunday Service',
            'status'    => 'draft',
        ]);
    }

    public function test_admin_can_show_a_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/dashboard/scheduling/plans/{$plan->id}");

        $response->assertOk();
    }

    public function test_admin_can_update_a_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Old Title',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/scheduling/plans/{$plan->id}", $this->planData(['title' => 'New Title']));

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', ['id' => $plan->id, 'title' => 'New Title']);
    }

    public function test_admin_can_delete_a_draft_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/plans/{$plan->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('service_plans', ['id' => $plan->id]);
    }

    public function test_admin_can_publish_a_plan_and_volunteers_are_notified(): void
    {
        Notification::fake();

        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $volunteer = User::factory()->create(['church_id' => $this->church->id]);
        $volunteer->assignRole('member');

        $planPos = ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);

        \App\Models\VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $planPos->id,
            'user_id'                 => $volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/scheduling/plans/{$plan->id}/publish");

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', ['id' => $plan->id, 'status' => 'published']);
        Notification::assertSentTo($volunteer, \App\Notifications\VolunteerAssigned::class);
    }

    public function test_admin_can_add_position_to_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/scheduling/plans/{$plan->id}/positions", [
                'serving_position_id' => $this->position->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plan_positions', [
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);
    }

    public function test_admin_can_remove_position_from_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $planPos = ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/plans/{$plan->id}/positions/{$planPos->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('service_plan_positions', ['id' => $planPos->id]);
    }
}
