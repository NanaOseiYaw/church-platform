<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'church_name'                 => 'Grace Community Church',
            'church_tagline'              => 'A Place to Belong',
            'timezone'                    => 'America/New_York',
            'denomination'                => 'Baptist',
            'country'                     => 'United States',
            'primary_color'               => '#6366f1',
            'admin_name'                  => 'John Smith',
            'admin_email'                 => 'john@grace.org',
            'admin_email_confirmation'    => 'john@grace.org',
            'admin_password'              => 'password123',
            'admin_password_confirmation' => 'password123',
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────────

    public function test_onboarding_page_returns_all_required_props(): void
    {
        $response = $this->get('/onboarding');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding/Index')
                ->has('timezones')
                ->has('colorPresets')
                ->has('denominations')
                ->has('countries')
            );
    }

    public function test_valid_submission_creates_church_and_admin_and_redirects(): void
    {
        $response = $this->post('/onboarding', $this->validPayload());

        $response->assertRedirect(route('onboarding.welcome'));
        $this->assertDatabaseHas('churches', ['name' => 'Grace Community Church']);
        $church = \App\Models\Church::where('name', 'Grace Community Church')->firstOrFail();
        $this->assertDatabaseHas('users', ['email' => 'john@grace.org', 'church_id' => $church->id]);
        $this->assertAuthenticated();
    }

    public function test_email_confirmation_mismatch_fails_validation(): void
    {
        $response = $this->post('/onboarding', $this->validPayload([
            'admin_email_confirmation' => 'typo@grace.org',
        ]));

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_password_confirmation_mismatch_fails_validation(): void
    {
        $response = $this->post('/onboarding', $this->validPayload([
            'admin_password_confirmation' => 'different456',
        ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_duplicate_email_fails_validation(): void
    {
        $church = Church::create([
            'name'              => 'Existing Church',
            'subscription_plan' => 'free',
            'is_active'         => true,
            'settings'          => [],
        ]);
        User::factory()->create([
            'church_id' => $church->id,
            'email'     => 'taken@grace.org',
        ]);

        $response = $this->post('/onboarding', $this->validPayload([
            'admin_email'              => 'taken@grace.org',
            'admin_email_confirmation' => 'taken@grace.org',
        ]));

        $response->assertSessionHasErrors('admin_email');
    }
}
