<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function superAdmin(): User
    {
        $user = User::factory()->create(['church_id' => null]);
        $user->assignRole('super_admin');
        return $user;
    }

    private function regularUser(): User
    {
        $church = Church::create(['name' => 'Regular Church ' . uniqid()]);
        $user   = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole('member');
        return $user;
    }

    // ── auth gate ──────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_super_admin(): void
    {
        $response = $this->get('/super-admin');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_super_admin(): void
    {
        $this->actingAs($this->regularUser());
        $response = $this->get('/super-admin');
        $response->assertStatus(403);
    }

    // ── church list ────────────────────────────────────────────────────────

    public function test_super_admin_can_view_church_list(): void
    {
        Church::create(['name' => 'Alpha Church']);
        Church::create(['name' => 'Beta Church']);
        Church::create(['name' => 'Gamma Church']);

        $response = $this->actingAs($this->superAdmin())->get('/super-admin');

        $response->assertStatus(200)
                 ->assertInertia(fn ($page) => $page
                     ->component('SuperAdmin/Index')
                     ->has('churches.data')
                 );
    }

    public function test_church_list_includes_user_count(): void
    {
        $church = Church::create(['name' => 'Count Church']);
        User::factory()->count(2)->create(['church_id' => $church->id]);

        $this->actingAs($this->superAdmin())->get('/super-admin')
             ->assertInertia(fn ($page) => $page
                 ->has('churches.data.0.users_count')
                 ->where('churches.data.0.users_count', 2)
             );
    }

    // ── create church ──────────────────────────────────────────────────────

    public function test_super_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin())->get('/super-admin/churches/create');

        $response->assertStatus(200)
                 ->assertInertia(fn ($page) => $page
                     ->component('SuperAdmin/Create')
                     ->has('timezones')
                     ->has('denominations')
                 );
    }

    public function test_super_admin_can_create_a_church(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/super-admin/churches', [
            'church_name'    => 'New Life Church',
            'admin_name'     => 'Pastor Bob',
            'admin_email'    => 'bob@newlife.org',
            'admin_password' => 'password123',
            'timezone'       => 'UTC',
        ]);

        $response->assertRedirect('/super-admin');
        $this->assertDatabaseHas('churches', ['name' => 'New Life Church']);
        $this->assertDatabaseHas('users', ['email' => 'bob@newlife.org']);
    }

    public function test_creating_church_does_not_log_out_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/super-admin/churches', [
            'church_name'    => 'Grace Fellowship',
            'admin_name'     => 'Pastor Jane',
            'admin_email'    => 'jane@grace.org',
            'admin_password' => 'password123',
            'timezone'       => 'UTC',
        ]);

        // Super admin should still be authenticated
        $this->assertAuthenticatedAs($admin);
    }

    // ── toggle active ──────────────────────────────────────────────────────

    public function test_super_admin_can_suspend_a_church(): void
    {
        $church = Church::create(['name' => 'Active Church', 'is_active' => true]);

        $this->actingAs($this->superAdmin())
             ->patch("/super-admin/{$church->id}/toggle-active")
             ->assertRedirect();

        $this->assertDatabaseHas('churches', [
            'id'        => $church->id,
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_reactivate_a_church(): void
    {
        $church = Church::create(['name' => 'Suspended Church', 'is_active' => false]);

        $this->actingAs($this->superAdmin())
             ->patch("/super-admin/{$church->id}/toggle-active")
             ->assertRedirect();

        $this->assertDatabaseHas('churches', [
            'id'        => $church->id,
            'is_active' => true,
        ]);
    }

    // ── impersonation ──────────────────────────────────────────────────────

    public function test_super_admin_can_start_impersonating_a_church(): void
    {
        $church = Church::create(['name' => 'Impersonate Church']);

        $response = $this->actingAs($this->superAdmin())
                         ->post("/super-admin/{$church->id}/impersonate");

        $response->assertRedirect('/dashboard')
                 ->assertSessionHas('super_admin_impersonating', $church->id);
    }

    public function test_super_admin_can_stop_impersonating(): void
    {
        $church = Church::create(['name' => 'Stop Impersonate Church']);

        $this->actingAs($this->superAdmin())
             ->withSession(['super_admin_impersonating' => $church->id])
             ->delete('/super-admin/impersonate')
             ->assertRedirect('/super-admin')
             ->assertSessionMissing('super_admin_impersonating');
    }
}
