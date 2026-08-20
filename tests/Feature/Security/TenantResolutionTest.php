<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\ResolveTenant;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * ResolveTenant decides which church every request operates on, so it is the
 * hinge the whole multi-tenant model hangs from. Its single-tenant fallback
 * (`Church::orderBy('id')->first()`) exists to give anonymous visitors a public
 * site — it must never be applied to an authenticated user, or a user whose
 * own church has gone away silently inherits someone else's tenant.
 *
 * users.church_id is `nullOnDelete`, so deleting a church nulls its members'
 * church_id rather than removing them — which is exactly how a church_admin
 * ends up authenticated with no tenant of their own.
 */
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function resolveFor(?User $user): ?Church
    {
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        $resolved = null;
        (new ResolveTenant())->handle($request, function () use (&$resolved) {
            $resolved = app('church');

            return response('ok');
        });

        return $resolved;
    }

    public function test_guest_falls_back_to_the_single_tenant(): void
    {
        $church = Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);

        $this->assertSame($church->id, $this->resolveFor(null)?->id);
    }

    public function test_authenticated_user_resolves_to_their_own_church(): void
    {
        $first  = Church::create(['name' => 'First Church', 'is_active' => true]);
        $second = Church::create(['name' => 'Second Church', 'is_active' => true]);

        $user = User::factory()->create(['church_id' => $second->id]);
        $user->assignRole('church_admin');

        $resolved = $this->resolveFor($user);

        $this->assertSame($second->id, $resolved?->id);
        $this->assertNotSame($first->id, $resolved?->id);
    }

    public function test_super_admin_resolves_to_no_church(): void
    {
        Church::create(['name' => 'COP Amsterdam', 'is_active' => true]);

        $superAdmin = User::factory()->create(['church_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->assertNull($this->resolveFor($superAdmin));
    }

    /**
     * The regression this guards: an orphaned church_admin (church deleted →
     * church_id nulled) must NOT be handed the first church in the table, which
     * would silently make them an administrator of a tenant they never belonged to.
     */
    public function test_orphaned_user_does_not_inherit_another_churchs_tenant(): void
    {
        $victim = Church::create(['name' => 'Victim Church', 'is_active' => true]);

        $orphan = User::factory()->create(['church_id' => null]);
        $orphan->assignRole('church_admin');

        $resolved = $this->resolveFor($orphan);

        $this->assertNotSame(
            $victim->id,
            $resolved?->id,
            'An orphaned church_admin inherited an unrelated church as their tenant.'
        );
        $this->assertNull($resolved);
    }
}
