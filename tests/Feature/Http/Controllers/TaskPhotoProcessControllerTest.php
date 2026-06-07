<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskPhotoProcessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributes_inactive_room_planning_task_photo(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config([
            'services.storage_share_api.url' => 'https://storage-share-api.test/api',
            'services.storage_share_api.token' => 'test-token',
        ]);

        Http::fake([
            'storage-share-api.test/*' => Http::response(['notification_id' => 123], 200),
        ]);

        $user = User::factory()->create(['role' => Role::ADMIN->value]);
        $location = Location::factory()->create(['external_id' => 'space-123']);
        $planning = Planning::factory()->create();
        $planningTask = PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'location_id' => $location->id,
            'task_id' => null,
            'room_identifier' => '1.22',
        ]);

        $completion = $planningTask->completions()->create([
            'user_id' => $user->id,
            'comment' => 'Controle afgerond',
            'is_fully_completed' => true,
        ]);

        $completion->photos()->create([
            'file_path' => 'planning-task-completion-photos/inactive-room.jpg',
            'room' => null,
        ]);

        $response = $this->actingAs($user)->post(
            route('photo-workflow.planning-task.distribute', $planningTask),
            ['room' => '1.22']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://storage-share-api.test/api/photo-process/distribute'
            && $request['space_id'] === 'space-123'
            && $request['room_identifier'] === '1.22'
            && $request['planning_task_id'] === $planningTask->id
        );
    }
}
