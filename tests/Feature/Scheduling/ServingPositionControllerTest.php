<?php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServingPosition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingPositionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $member;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin  = User::factory()->create(['church_id' => $this->church->id]);
        $this->member = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');
        $this->member->assignRole('member');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media Team',
            'slug'      => 'media-team',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_positions(): void
    {
        ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/scheduling/positions');

        $response->assertOk();
    }

    public function test_admin_can_create_position(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/positions', [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
                'description'   => 'Operates the main camera',
                'sort_order'    => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('serving_positions', [
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);
    }

    public function test_admin_can_update_position(): void
    {
        $position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Old Name',
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/scheduling/positions/{$position->id}", [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('serving_positions', ['id' => $position->id, 'name' => 'Camera Operator']);
    }

    public function test_admin_can_delete_position(): void
    {
        $position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/positions/{$position->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('serving_positions', ['id' => $position->id]);
    }

    public function test_member_cannot_manage_positions(): void
    {
        $response = $this->actingAs($this->member)
            ->post('/dashboard/scheduling/positions', [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
            ]);

        $response->assertForbidden();
    }
}
