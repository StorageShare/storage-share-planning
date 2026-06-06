<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\User;
use App\Mail\NewApiTaskReceivedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.external_api.secret', 'test-secret');
        Mail::fake();
    }

    public function test_can_create_normal_task_via_api(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $payload = [
            'title' => 'Normal API Task',
            'description' => 'Created via normal API',
            'location_id' => $location->id,
            'priority' => 'high',
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->postJson('/api/v1/external/tasks', $payload, [
            'X-Api-Signature' => $signature,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('task.title', 'Normal API Task');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Normal API Task',
            'location_id' => $location->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        Mail::assertSent(NewApiTaskReceivedMail::class, function ($mail) {
            return $mail->hasTo('planning@storage-share.nl');
        });
    }

    public function test_cannot_create_normal_task_without_signature(): void
    {
        $location = Location::factory()->create();

        $payload = [
            'title' => 'Unauthorized Task',
            'description' => 'Should fail',
            'location_id' => $location->id,
        ];

        $response = $this->postJson('/api/v1/external/tasks', $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('tasks', ['title' => 'Unauthorized Task']);
    }
}
