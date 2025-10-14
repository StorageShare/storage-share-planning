<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Planning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilities_coordinator_can_access_plannings_index_and_show(): void
    {
        $user = User::factory()->create(['role' => Role::FACILITIES_COORDINATOR->value]);
        $planning = Planning::factory()->create();

        $this->actingAs($user)->get(route('plannings.index'))->assertOk();
        $this->actingAs($user)->get(route('plannings.show', $planning))->assertOk();
    }

    public function test_guest_cannot_access_plannings_index(): void
    {
        $this->get(route('plannings.index'))->assertRedirect();
    }
}
