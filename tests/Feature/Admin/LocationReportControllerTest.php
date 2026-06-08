<?php

namespace Feature\Admin;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-08 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_non_admin_cannot_access_report(): void
    {
        $user = User::factory()->create(['role' => Role::GEBRUIKER->value]);

        $response = $this->actingAs($user)->get(route('admin.locations.report'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_report(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.locations.report'));

        $response->assertOk();
        $response->assertSee('Locatie Overzicht');
    }

    public function test_report_shows_visit_and_maintenance_aggregates(): void
    {
        $admin = User::factory()->admin()->create();

        $location = Location::factory()->create(['name' => 'Rapport Test Locatie']);
        $otherLocation = Location::factory()->create(['name' => 'Andere Locatie']);

        $recentPlanning = Planning::factory()->create(['planned_date' => now()->subDays(5)]);
        $olderPlanning = Planning::factory()->create(['planned_date' => now()->subDays(200)]);
        $oldPlanning = Planning::factory()->create(['planned_date' => now()->subDays(400)]);

        $controlerondeTask = Task::factory()->for($location)->create([
            'title' => 'Controleronde',
            'status' => TaskStatus::COMPLETED->value,
        ]);

        $schoonmaakTask = Task::factory()->for($location)->create([
            'title' => 'Schoonmaken',
            'status' => TaskStatus::COMPLETED->value,
        ]);

        PlanningTask::factory()->create([
            'planning_id' => $recentPlanning->id,
            'task_id' => $controlerondeTask->id,
            'location_id' => null,
            'title' => 'Controleronde',
            'completed_at' => now()->subDays(5),
            'status' => TaskStatus::COMPLETED,
        ]);

        PlanningTask::factory()->create([
            'planning_id' => $olderPlanning->id,
            'location_id' => $location->id,
            'task_id' => $schoonmaakTask->id,
            'title' => 'Schoonmaken',
            'completed_at' => now()->subDays(20),
            'status' => TaskStatus::COMPLETED,
        ]);

        PlanningTask::factory()->create([
            'planning_id' => $oldPlanning->id,
            'location_id' => $location->id,
            'title' => 'Algemene taak',
            'completed_at' => now()->subDays(400),
            'status' => TaskStatus::COMPLETED,
        ]);

        PlanningTask::factory()->create([
            'planning_id' => $recentPlanning->id,
            'location_id' => $otherLocation->id,
            'title' => 'Controleronde',
            'completed_at' => null,
            'status' => TaskStatus::OPEN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.locations.report', [
            'search_term' => 'Rapport Test',
        ]));

        $response->assertOk();
        $response->assertSee('Rapport Test Locatie');
        $response->assertSee(now()->subDays(5)->format('d-m-Y'));
        $response->assertSee(now()->subDays(20)->format('d-m-Y'));
        $response->assertViewHas('locations', function ($paginator): bool {
            $reportLocation = $paginator->firstWhere('name', 'Rapport Test Locatie');

            if ($reportLocation === null) {
                return false;
            }

            return (int) $reportLocation->visits_30d === 2
                && (int) $reportLocation->visits_365d === 2
                && $reportLocation->last_controleronde_at !== null
                && $reportLocation->last_schoonmaak_at !== null
                && Carbon::parse($reportLocation->last_visit_at)->isSameDay(now()->subDays(5));
        });
        $response->assertDontSee('Andere Locatie');
    }
}
