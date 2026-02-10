<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Planning;
use App\Models\PlanningComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningCommentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_own_comment(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $comment = PlanningComment::create([
            'planning_id' => $planning->id,
            'user_id' => $user->id,
            'comment' => 'Oude opmerking',
        ]);

        $response = $this->actingAs($user)
            ->put(route('planning-comments.update', $comment), [
                'notes' => 'Bijgewerkte opmerking',
            ]);

        $response->assertOk();
        $this->assertEquals('Bijgewerkte opmerking', $comment->fresh()->comment);
    }

    public function test_user_cannot_update_others_comment(): void
    {
        $user1 = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $user2 = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $comment = PlanningComment::create([
            'planning_id' => $planning->id,
            'user_id' => $user1->id,
            'comment' => 'Oude opmerking',
        ]);

        $response = $this->actingAs($user2)
            ->put(route('planning-comments.update', $comment), [
                'notes' => 'Poging tot wijziging',
            ]);

        $response->assertForbidden();
        $this->assertEquals('Oude opmerking', $comment->fresh()->comment);
    }

    public function test_admin_can_update_any_comment(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();
        $comment = PlanningComment::create([
            'planning_id' => $planning->id,
            'user_id' => $user->id,
            'comment' => 'Oude opmerking',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('planning-comments.update', $comment), [
                'notes' => 'Admin wijziging',
            ]);

        $response->assertOk();
        $this->assertEquals('Admin wijziging', $comment->fresh()->comment);
    }

    public function test_user_can_add_photos_when_updating_comment(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $comment = PlanningComment::create([
            'planning_id' => $planning->id,
            'user_id' => $user->id,
            'comment' => 'Oude opmerking',
        ]);

        $photo1 = UploadedFile::fake()->image('test1.jpg');
        $photo2 = UploadedFile::fake()->image('test2.jpg');

        $response = $this->actingAs($user)
            ->post(route('planning-comments.update', $comment), [
                '_method' => 'PUT',
                'notes' => 'Bijgewerkt met foto\'s',
                'photos' => [$photo1, $photo2],
            ]);

        $response->assertOk();
        $this->assertEquals('Bijgewerkt met foto\'s', $comment->fresh()->comment);
        $this->assertCount(2, $comment->fresh()->photos);
    }
}
