<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\EndChecklistItem;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_planning_status_updates_to_completed_when_last_checklist_item_approved(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create(['status' => 'in_progress']);

        // Create a completed task
        PlanningTask::create([
            'planning_id' => $planning->id,
            'title' => 'Test Task',
            'description' => 'Test description',
            'status' => TaskStatus::COMPLETED,
        ]);

        // Create a pending checklist item
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Check door',
            'status' => 'pending',
        ]);

        // Initial state check
        $planning->refresh();
        $planning->checkAndUpdateStatus();
        $this->assertEquals('pending_end_checklist', $planning->status);

        // Act: Approve the checklist item
        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('admin.end-checklist.approve', $item));

        $response->assertRedirect();

        // Assert
        $planning->refresh();
        $this->assertEquals('completed', $planning->status);
    }
}
