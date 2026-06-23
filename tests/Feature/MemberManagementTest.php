<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeChurch(): Church
    {
        return Church::create(['name' => 'Test Church ' . uniqid(), 'is_active' => true]);
    }

    private function actAs(Church $church, string $role): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole($role);

        app()->instance('church',    $church);
        app()->instance('church.id', $church->id);
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_can_create_member_with_temporary_password(): void
    {
        $church = $this->makeChurch();
        $this->actAs($church, 'church_admin');

        $this->post('/dashboard/members', [
            'name'  => 'Kwame Mensah',
            'email' => 'kwame@example.com',
            'role'  => 'coordinator',
        ])->assertRedirect(route('dashboard.members.index'))
          ->assertSessionHas('newMember');

        $member = User::where('email', 'kwame@example.com')->first();

        $this->assertNotNull($member);
        $this->assertSame($church->id, $member->church_id);
        $this->assertTrue($member->hasRole('coordinator'));
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue((bool) $member->is_active);

        // The flashed temporary password must actually authenticate the new account.
        $temp = session('newMember')['temp_password'];
        $this->assertTrue(Hash::check($temp, $member->password));
    }

    public function test_non_admin_cannot_create_member(): void
    {
        $church = $this->makeChurch();
        $this->actAs($church, 'member');

        $this->post('/dashboard/members', [
            'name'  => 'Ama Darko',
            'email' => 'ama@example.com',
            'role'  => 'member',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ama@example.com']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $church = $this->makeChurch();
        $this->actAs($church, 'church_admin');

        User::factory()->create(['church_id' => $church->id, 'email' => 'taken@example.com']);

        $this->post('/dashboard/members', [
            'name'  => 'Someone',
            'email' => 'taken@example.com',
            'role'  => 'member',
        ])->assertSessionHasErrors('email');
    }

    public function test_invalid_role_is_rejected(): void
    {
        $church = $this->makeChurch();
        $this->actAs($church, 'church_admin');

        $this->post('/dashboard/members', [
            'name'  => 'Bad Role',
            'email' => 'badrole@example.com',
            'role'  => 'super_admin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'badrole@example.com']);
    }
}
