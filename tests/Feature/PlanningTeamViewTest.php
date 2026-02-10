<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Planning;
use App\Models\User;
use App\Services\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningTeamViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_is_shown_in_my_planning_summary(): void
    {
        Carbon::setTestNow('2025-10-17 08:00:00');

        $user1 = User::factory()->create(['name' => 'Jan Janssen', 'role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $user2 = User::factory()->create(['name' => 'Piet Pietersen', 'role' => Role::ALGEMEEN_MEDEWERKER->value]);

        $planning = Planning::factory()->create([
            'planned_date' => Carbon::today(),
            'start_address' => 'kantoor',
        ]);
        $planning->users()->attach([$user1->id, $user2->id]);

        $mock = $this->createMock(TravelTimeService::class);
        $mock->method('calculateTravelTimesForSequence')->willReturn([]);
        $mock->method('formatDuration')->willReturn('0 min');
        $this->app->instance(TravelTimeService::class, $mock);

        $response = $this->actingAs($user1)
            ->get(route('my-planning.show'));

        $response->assertOk();
        $response->assertSee('Team:');
        $response->assertSee('Jan Janssen');
        $response->assertSee('Piet Pietersen');
        $response->assertSee('Jan Janssen en Piet Pietersen');

        Carbon::setTestNow();
    }
}
