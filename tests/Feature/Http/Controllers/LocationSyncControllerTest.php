<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);

        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_admin_can_trigger_sync_successfully(): void
    {
        // Mock the Artisan call to succeed
        Artisan::shouldReceive('call')
            ->once()
            ->with('locations:sync')
            ->andReturn(0);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.sync'));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('success');
    }

    public function test_admin_sees_error_when_command_returns_non_zero_exit_code(): void
    {
        // Mock the Artisan call to fail and provide output
        Artisan::shouldReceive('call')
            ->once()
            ->with('locations:sync')
            ->andReturn(1);
        Artisan::shouldReceive('output')
            ->andReturn('Some error occurred.');

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.sync'));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('error');
    }

    public function test_admin_sees_error_when_exception_is_thrown(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('locations:sync')
            ->andThrow(new \Exception('Boom'));

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.sync'));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('error', function ($msg) {
            return is_string($msg) && str_contains($msg, 'Boom');
        });
    }

    public function test_non_admin_cannot_access_sync_route(): void
    {
        $user = User::factory()->create(); // non-admin by default

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.sync'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // Provide CSRF token to avoid 419 from middleware
        $response = $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('locations.sync'));

        $response->assertRedirect(route('login'));
    }
}
