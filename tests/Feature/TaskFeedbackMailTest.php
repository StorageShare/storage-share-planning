<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use App\Models\User;
use App\Enums\Role;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\TaskCompletedApprovedMail;
use Tests\TestCase;

class TaskFeedbackMailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
    }

    public function test_mail_is_sent_when_task_is_approved_and_has_emails(): void
    {
        Mail::fake();

        $location = Location::factory()->create();
        $planning = Planning::factory()->create();
        $planning->locations()->attach($location->id, ['sort_order' => 0]);
        $planningTask = PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'feedback_emails' => 'test@example.com, second@example.com',
            'feedback_owner_name' => 'John Doe',
            'status' => TaskStatus::REVIEW
        ]);

        $completion = PlanningTaskCompletion::factory()->create([
            'planning_task_id' => $planningTask->id,
            'user_id' => $this->admin->id,
            'comment' => 'Taak is klaar'
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('plannings.tasks.approve', $planningTask), [
                'review_notes' => 'Goed gedaan'
            ]);

        $response->assertStatus(302);

        Mail::assertQueued(TaskCompletedApprovedMail::class, function ($mail) use ($planningTask) {
            return $mail->hasTo('test@example.com') &&
                   $mail->hasTo('second@example.com') &&
                   $mail->planningTask->id === $planningTask->id;
        });
    }

    public function test_mail_is_not_sent_when_no_emails_provided(): void
    {
        Mail::fake();

        $location = Location::factory()->create();
        $planning = Planning::factory()->create();
        $planning->locations()->attach($location->id, ['sort_order' => 0]);
        $planningTask = PlanningTask::factory()->create([
            'planning_id' => $planning->id,
            'feedback_emails' => null,
            'status' => TaskStatus::REVIEW
        ]);

        $completion = PlanningTaskCompletion::factory()->create([
            'planning_task_id' => $planningTask->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('plannings.tasks.approve', $planningTask), [
                'review_notes' => 'Goed gedaan'
            ]);

        Mail::assertNothingSent();
    }
}
