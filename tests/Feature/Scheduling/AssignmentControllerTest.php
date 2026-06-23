<?php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Models\User;
use App\Models\VolunteerAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $volunteer;
    private ServicePlan $plan;
    private ServicePlanPosition $planPos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church    = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin     = User::factory()->create(['church_id' => $this->church->id]);
        $this->volunteer = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');
        $this->volunteer->assignRole('member');

        $dept = Department::create([
            'church_id' => $this->church->id, 'name' => 'Media', 'slug' => 'media', 'is_active' => true,
        ]);
        $servingPos = ServingPosition::create([
            'church_id' => $this->church->id, 'department_id' => $dept->id, 'name' => 'Camera',
        ]);
        $this->plan = ServicePlan::create([
            'church_id' => $this->church->id, 'title' => 'Sunday', 'scheduled_at' => now()->addWeek(),
            'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
        $this->planPos = ServicePlanPosition::create([
            'service_plan_id' => $this->plan->id, 'serving_position_id' => $servingPos->id,
        ]);
    }

    public function test_admin_can_assign_a_volunteer(): void
    {
        Notification::fake();

        $this->plan->update(['status' => 'published', 'published_at' => now()]);

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', [
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                  => $this->volunteer->id,
            'status'                   => 'pending',
        ]);
        Notification::assertSentTo($this->volunteer, \App\Notifications\VolunteerAssigned::class);
    }

    public function test_cannot_assign_same_volunteer_twice(): void
    {
        VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_admin_can_remove_an_assignment(): void
    {
        Notification::fake();

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/assignments/{$assignment->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('volunteer_assignments', ['id' => $assignment->id]);
        Notification::assertSentTo($this->volunteer, \App\Notifications\VolunteerRemoved::class);
    }

    public function test_volunteer_can_confirm_assignment(): void
    {
        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', [
            'id' => $assignment->id, 'status' => 'confirmed',
        ]);
    }

    public function test_volunteer_decline_notifies_plan_creator(): void
    {
        Notification::fake();

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'declined',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', ['id' => $assignment->id, 'status' => 'declined']);
        Notification::assertSentTo($this->admin, \App\Notifications\VolunteerDeclined::class);
    }

    public function test_volunteer_cannot_respond_for_another_persons_assignment(): void
    {
        $other = User::factory()->create(['church_id' => $this->church->id]);
        $other->assignRole('member');

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($other)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'confirmed',
            ]);

        $response->assertForbidden();
    }

    public function test_volunteer_is_notified_immediately_when_assigned_to_draft_plan(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        $response->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->volunteer,
            \App\Notifications\VolunteerAssigned::class,
        );
    }

    public function test_volunteer_assigned_notification_has_correct_format(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->volunteer,
            \App\Notifications\VolunteerAssigned::class,
            function ($notification) {
                $data = $notification->toArray($this->volunteer);
                return isset($data['title'], $data['body'], $data['action_url'])
                    && $data['type'] === 'scheduling.assigned'
                    && $data['action_url'] === '/dashboard/scheduling/my-schedule';
            }
        );
    }
}
