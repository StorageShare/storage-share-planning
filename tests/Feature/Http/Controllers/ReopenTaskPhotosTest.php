<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReopenTaskPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_reopen_returns_photos_from_latest_completion()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $pt = PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'status' => TaskStatus::REVIEW,
            'completed_notes' => 'Old notes',
        ]);

        $completion = $pt->completions()->create([
            'user_id' => $user->id,
            'comment' => 'Old notes',
            'is_fully_completed' => true,
        ]);

        $photo = $completion->photos()->create([
            'file_path' => 'photos/test.jpg',
        ]);

        $response = $this->actingAs($user)
            ->post(route('plannings.tasks.reopen', [$planning, $pt]));

        $response->assertStatus(200);
        $response->assertJsonPath('task.status', 'open');
        $response->assertJsonPath('task.photos.0.url', $photo->url);
    }
}
