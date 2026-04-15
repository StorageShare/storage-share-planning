<?php

namespace Tests\Feature\Console\Commands;

use App\Mail\PlanningReadyNotificationMail;
use App\Models\Planning;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPlanningNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_notifications_for_tomorrow_planning(): void
    {
        Mail::fake();

        // 1. Create a planning for tomorrow with users
        $tomorrow = Carbon::tomorrow();
        $planning = Planning::factory()->create([
            'planned_date' => $tomorrow,
        ]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $planning->users()->attach([$user1->id, $user2->id]);

        // 2. Create a planning for today (should NOT be sent)
        $today = Carbon::today();
        $todayPlanning = Planning::factory()->create([
            'planned_date' => $today,
        ]);
        $user3 = User::factory()->create();
        $todayPlanning->users()->attach([$user3->id]);

        // 3. Create a planning for day after tomorrow (should NOT be sent)
        $afterTomorrow = Carbon::tomorrow()->addDay();
        $afterTomorrowPlanning = Planning::factory()->create([
            'planned_date' => $afterTomorrow,
        ]);
        $user4 = User::factory()->create();
        $afterTomorrowPlanning->users()->attach([$user4->id]);

        // 4. Run the command
        $this->artisan('app:send-planning-notifications')
            ->assertExitCode(0);

        // 5. Assertions
        Mail::assertSent(PlanningReadyNotificationMail::class, function ($mail) use ($user1, $planning) {
            return $mail->hasTo($user1->email) && $mail->planning->id === $planning->id;
        });

        Mail::assertSent(PlanningReadyNotificationMail::class, function ($mail) use ($user2, $planning) {
            return $mail->hasTo($user2->email) && $mail->planning->id === $planning->id;
        });

        Mail::assertNotSent(PlanningReadyNotificationMail::class, function ($mail) use ($user3) {
            return $mail->hasTo($user3->email);
        });

        Mail::assertNotSent(PlanningReadyNotificationMail::class, function ($mail) use ($user4) {
            return $mail->hasTo($user4->email);
        });
    }

    public function test_it_does_not_crash_if_no_users_assigned(): void
    {
        Mail::fake();

        // 1. Create a planning for tomorrow without users
        $tomorrow = Carbon::tomorrow();
        Planning::factory()->create([
            'planned_date' => $tomorrow,
        ]);

        // 2. Run the command
        $this->artisan('app:send-planning-notifications')
            ->assertExitCode(0)
            ->expectsOutputToContain("Planning #1 heeft geen toegewezen gebruikers. Overslaan.");

        // 3. Assert no mail sent
        Mail::assertNothingSent();
    }
}
