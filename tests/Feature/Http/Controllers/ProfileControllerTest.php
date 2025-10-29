<?php

namespace Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_edit_displays_view_with_user(): void
    {
        $user = User::factory()->gebruiker()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertViewIs('profile.edit');
        $response->assertViewHas('user', function ($viewUser) use ($user) {
            return (int) $viewUser->id === (int) $user->id;
        });
    }

    public function test_update_changes_name_and_email_and_resets_verification_when_email_changed(): void
    {
        $user = User::factory()->gebruiker()->create([
            'email' => 'old@example.com',
            'email_verified_at' => Carbon::now(),
        ]);

        $payload = [
            '_token' => $this->token,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->patch(route('profile.update'), $payload);

        $resp->assertRedirect(route('profile.edit'));
        $resp->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at, 'Email verification should be reset when email changes');
    }

    public function test_update_validates_unique_email(): void
    {
        $user = User::factory()->gebruiker()->create(['email' => 'me@example.com']);
        $other = User::factory()->gebruiker()->create(['email' => 'taken@example.com']);

        $payload = [
            '_token' => $this->token,
            'name' => 'Name',
            'email' => 'taken@example.com', // already used by $other
        ];

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), $payload);

        $resp->assertSessionHasErrors('email');
    }

    public function test_destroy_deletes_account_and_logs_out(): void
    {
        $user = User::factory()->gebruiker()->create();

        $resp = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('profile.destroy'), ['_token' => $this->token]);

        $resp->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_guest_is_redirected_to_login_for_edit_update_destroy(): void
    {
        // edit
        $this->get(route('profile.edit'))->assertRedirect('/login');

        // update
        $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->patch(route('profile.update'), [
                '_token' => $this->token,
                'name' => 'X',
                'email' => 'x@example.com',
            ])->assertRedirect('/login');

        // destroy
        $this->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('profile.destroy'), ['_token' => $this->token])
            ->assertRedirect('/login');
    }
}
