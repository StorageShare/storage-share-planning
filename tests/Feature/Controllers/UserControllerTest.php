<?php

namespace Feature\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_index_renders_users_for_admin(): void
    {
        $admin = $this->makeAdmin();
        $u1 = User::factory()->create(['name' => 'Alpha']);
        $u2 = User::factory()->create(['name' => 'Beta']);

        $resp = $this->actingAs($admin)->get(route('users.index'));

        $resp->assertOk();
        $resp->assertViewIs('users.index');
        $resp->assertViewHas('users', function ($paginator) use ($u1, $u2) {
            $ids = $paginator->getCollection()->pluck('id')->all();
            $this->assertContains($u1->id, $ids);
            $this->assertContains($u2->id, $ids);
            return true;
        });
    }

    public function test_create_renders_with_roles(): void
    {
        $admin = $this->makeAdmin();
        $resp = $this->actingAs($admin)->get(route('users.create'));

        $resp->assertOk();
        $resp->assertViewIs('users.create');
        $resp->assertViewHas('roles', function ($roles) {
            // Expect Role::cases()
            $this->assertIsArray($roles) || $this->assertInstanceOf(\UnitEnum::class, $roles[0] ?? null);
            return count($roles) > 0;
        });
    }

    public function test_store_validates_and_creates_user_then_redirects(): void
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'role' => Role::GEBRUIKER->value,
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('users.store'), $payload);

        $resp->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
            'role' => Role::GEBRUIKER->value,
        ]);
    }

    public function test_store_rejects_invalid_role(): void
    {
        $admin = $this->makeAdmin();

        $payload = [
            'name' => 'Invalid Role',
            'email' => 'inv@example.com',
            'role' => 'not_a_role',
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('users.store'), $payload);

        $resp->assertSessionHasErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'inv@example.com']);
    }

    public function test_edit_renders_with_user_and_roles(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['role' => Role::GEBRUIKER->value]);

        $resp = $this->actingAs($admin)->get(route('users.edit', $user));

        $resp->assertOk();
        $resp->assertViewIs('users.edit');
        $resp->assertViewHas('user', function ($u) use ($user) {
            return (int) $u->id === (int) $user->id;
        });
        $resp->assertViewHas('roles');
    }

    public function test_update_changes_role_and_redirects(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['role' => Role::GEBRUIKER->value]);

        $payload = [
            'role' => Role::CUSTOMER_SERVICE->value,
            '_token' => $this->token,
        ];

        $resp = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('users.update', $user), $payload);

        $resp->assertRedirect(route('users.index'));
        $user->refresh();
        $this->assertEquals(Role::CUSTOMER_SERVICE->value, $user->role);
    }

    public function test_non_admin_is_forbidden_from_accessing_user_routes(): void
    {
        $user = User::factory()->create(['role' => Role::GEBRUIKER->value]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();

        $target = User::factory()->create();
        $this->actingAs($user)->get(route('users.edit', $target))->assertForbidden();
        $this->actingAs($user)->put(route('users.update', $target), [])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect('/login');
        $this->get(route('users.create'))->assertRedirect('/login');
        $this->post(route('users.store'))->assertRedirect('/login');
    }
}
