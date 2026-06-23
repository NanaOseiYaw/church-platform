<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastTemplate;
use App\Models\Church;
use App\Models\Department;
use App\Models\User;
use App\Services\BroadcastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Church     $church;
    private User       $admin;
    private User       $coordinator;
    private User       $member;
    private Department $dept;
    private Department $otherDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-' . uniqid(),
        ]);
        app()->instance('church.id', $this->church->id);

        $this->admin = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('church_admin');

        $this->coordinator = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->member = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->member->assignRole('member');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media Team',
            'slug'      => 'media-team',
            'is_active' => true,
        ]);
        $this->otherDept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Youth Team',
            'slug'      => 'youth-team',
            'is_active' => true,
        ]);

        // Attach coordinator to dept with coordinator pivot role
        $this->dept->members()->attach($this->coordinator->id, ['role' => 'coordinator']);
    }

    // COM-001: admin can create a draft broadcast
    public function test_admin_can_create_draft_broadcast(): void
    {
        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Test Broadcast',
                'subject'       => 'Hello Church',
                'body'          => 'Dear {{member_name}}, greetings!',
                'audience_type' => 'all_members',
                'action'        => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'church_id'     => $this->church->id,
            'title'         => 'Test Broadcast',
            'status'        => 'draft',
            'audience_type' => 'all_members',
        ]);
    }

    // COM-002: send action dispatches job and creates recipient rows
    public function test_send_action_dispatches_job_and_creates_recipients(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Live Broadcast',
                'subject'       => 'News',
                'body'          => 'Hello {{member_name}}',
                'audience_type' => 'all_members',
                'action'        => 'send',
            ])
            ->assertRedirect();

        $broadcast = Broadcast::where('title', 'Live Broadcast')->first();
        $this->assertNotNull($broadcast);

        $this->assertDatabaseHas('broadcast_recipients', [
            'broadcast_id' => $broadcast->id,
            'user_id'      => $this->admin->id,
        ]);

        Queue::assertPushed(SendBroadcastJob::class, fn ($job) => $job->broadcastId === $broadcast->id);
    }

    // COM-003: coordinator cannot send to a department they don't coordinate
    public function test_coordinator_cannot_send_to_other_department(): void
    {
        $this->actingAs($this->coordinator)
            ->post('/dashboard/communication/broadcasts', [
                'title'           => 'Sneaky Broadcast',
                'subject'         => 'Oops',
                'body'            => 'Hello',
                'audience_type'   => 'department',
                'audience_config' => ['department_id' => $this->otherDept->id],
                'action'          => 'send',
            ])
            ->assertForbidden();
    }

    // COM-004: coordinator CAN send to their own department
    public function test_coordinator_can_send_to_own_department(): void
    {
        Queue::fake();

        $this->actingAs($this->coordinator)
            ->post('/dashboard/communication/broadcasts', [
                'title'           => 'Dept Broadcast',
                'subject'         => 'Hi Team',
                'body'            => 'Meeting tonight',
                'audience_type'   => 'department',
                'audience_config' => ['department_id' => $this->dept->id],
                'action'          => 'send',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'title'  => 'Dept Broadcast',
            'status' => 'sending',
        ]);
    }

    // COM-005: member cannot create a broadcast
    public function test_member_cannot_create_broadcast(): void
    {
        $this->actingAs($this->member)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Spam',
                'subject'       => 'Hello',
                'body'          => 'World',
                'audience_type' => 'all_members',
                'action'        => 'send',
            ])
            ->assertForbidden();
    }

    // COM-006: schedule action sets status to scheduled
    public function test_schedule_action_sets_status_to_scheduled(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Future Broadcast',
                'subject'       => 'Coming Soon',
                'body'          => 'See you then',
                'audience_type' => 'all_members',
                'action'        => 'schedule',
                'scheduled_at'  => now()->addHours(2)->toISOString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'title'  => 'Future Broadcast',
            'status' => 'scheduled',
        ]);
    }

    // COM-007: using a template increments its usage_count
    public function test_template_usage_count_increments(): void
    {
        Queue::fake();

        $template = BroadcastTemplate::create([
            'church_id'   => $this->church->id,
            'created_by'  => $this->admin->id,
            'name'        => 'My Template',
            'subject'     => 'Weekly Update',
            'body'        => 'Hello {{member_name}}',
            'usage_count' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Template Broadcast',
                'subject'       => 'Weekly Update',
                'body'          => 'Hello {{member_name}}',
                'audience_type' => 'all_members',
                'template_id'   => $template->id,
                'action'        => 'draft',
            ]);

        $this->assertDatabaseHas('broadcast_templates', [
            'id'          => $template->id,
            'usage_count' => 1,
        ]);
    }

    // COM-008: member cannot view communication dashboard
    public function test_member_cannot_view_communication_dashboard(): void
    {
        $this->actingAs($this->member)
            ->get('/dashboard/communication')
            ->assertForbidden();
    }

    // COM-009: admin can view communication dashboard
    public function test_admin_can_view_communication_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard/communication')
            ->assertOk();
    }

    // COM-010: coordinator can view dashboard (has communication.view)
    public function test_coordinator_can_view_communication_dashboard(): void
    {
        $this->actingAs($this->coordinator)
            ->get('/dashboard/communication')
            ->assertOk();
    }

    // COM-011: resolve-count returns correct count for all_members
    public function test_resolve_count_returns_member_count(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/dashboard/communication/audiences/resolve-count?audience_type=all_members')
            ->assertOk()
            ->assertJsonPath('count', 3); // admin + coordinator + member
    }

    // COM-012: renderBody replaces all three variables
    public function test_render_body_replaces_variables(): void
    {
        $broadcast = new Broadcast();
        $broadcast->church_id     = $this->church->id;
        $broadcast->audience_type = 'all_members';
        $broadcast->setRelation('church', $this->church);

        $service  = app(BroadcastService::class);
        $rendered = $service->renderBody(
            'Hi {{member_name}}, welcome to {{church_name}}. This is from {{department_name}}.',
            $this->member,
            $broadcast,
        );

        $this->assertStringContainsString($this->member->name, $rendered);
        $this->assertStringContainsString($this->church->name, $rendered);
        $this->assertStringNotContainsString('{{member_name}}', $rendered);
        $this->assertStringNotContainsString('{{church_name}}', $rendered);
        $this->assertStringNotContainsString('{{department_name}}', $rendered);
    }
}
