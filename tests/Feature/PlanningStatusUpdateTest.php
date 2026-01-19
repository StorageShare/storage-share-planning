<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\EndChecklistItem;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_status_updates_to_completed_when_last_checklist_item_approved(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create(['status' => 'in_progress']);

        // Create a completed task
        PlanningTask::create([
            'planning_id' => $planning->id,
            'title' => 'Test Task',
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
            ->post(route('admin.end-checklist.approve', $item));

        $response->assertRedirect();

        // Assert
        $planning->refresh();
        $this->assertEquals('completed', $planning->status);
    }
}
