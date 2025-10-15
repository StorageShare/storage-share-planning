<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\User;
use App\Models\Benodigdheid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DefaultTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);

        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_index_renders_with_pagination_and_sort_search(): void
    {
        // Create some default tasks
        DefaultTask::create(['title' => 'Foo Task', 'description' => 'Alpha']);
        DefaultTask::create(['title' => 'Bar Task', 'description' => 'Beta']);
        DefaultTask::create(['title' => 'Zed Task', 'description' => 'Gamma']);

        // Valid sort and search
        $response = $this->actingAs($this->admin)
            ->get(route('default-tasks.index', [
                'sort_by' => 'title',
                'sort_direction' => 'asc',
                'search_term' => 'Task',
            ]));

        $response->assertOk();
        $response->assertViewIs('default-tasks.index');
        $response->assertViewHas('defaultTasks', function ($paginator) {
            return $paginator->total() >= 3; // tasks exist
        });

        // Invalid sort falls back
        $response = $this->actingAs($this->admin)
            ->get(route('default-tasks.index', [
                'sort_by' => 'invalid_column',
                'sort_direction' => 'nope',
            ]));

        $response->assertOk();
        $response->assertViewIs('default-tasks.index');
    }

    public function test_create_displays_form_with_dependencies(): void
    {
        $user = User::factory()->create();
        Location::factory()->count(2)->create();
        // ensure available door types present
        Location::factory()->create(['type_deur' => 'Houten deur']);
        Benodigdheid::create(['naam' => 'Emmer', 'beschrijving' => '', 'created_by' => $user->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('default-tasks.create'));

        $response->assertOk();
        $response->assertViewIs('default-tasks.create');
        $response->assertViewHasAll(['locations', 'benodigdheden', 'availableDoorTypes']);
    }

    public function test_store_with_applies_to_all_locations_syncs_all(): void
    {
        $user = User::factory()->create();
        $locs = Location::factory()->count(3)->create();
        $b1 = Benodigdheid::create(['naam' => 'Mop', 'beschrijving' => '', 'created_by' => $user->id]);
        $b2 = Benodigdheid::create(['naam' => 'Emmer', 'beschrijving' => '', 'created_by' => $user->id]);

        $payload = [
            'title' => 'Daily Sweep',
            'description' => 'Sweep all floors',
            'applies_to_all_locations' => 'on',
            'benodigdheden' => [$b1->id, $b2->id],
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('default-tasks.store'), $payload);

        $response->assertRedirect(route('default-tasks.index'));
        $response->assertSessionHas('success');

        $task = DefaultTask::first();
        $this->assertNotNull($task);
        $this->assertEquals($this->admin->id, $task->creator?->id);
        $this->assertEqualsCanonicalizing($locs->pluck('id')->all(), $task->locations()->pluck('locations.id')->all());
        $this->assertEqualsCanonicalizing([$b1->id, $b2->id], $task->benodigdheden()->pluck('benodigdheden.id')->all());
    }

    public function test_store_with_door_types_syncs_matching_locations_case_insensitive(): void
    {
        // Locations with mixed-case door types
        $l1 = Location::factory()->create(['type_deur' => 'Houten deur']);
        $l2 = Location::factory()->create(['type_deur' => 'GLAZEN DEUR']);
        $l3 = Location::factory()->create(['type_deur' => 'Overhead deur']);

        $payload = [
            'title' => 'Door Cleaning',
            'description' => 'Clean doors',
            'applies_to_door_types' => 'on',
            'door_types' => ['  houten deur ', 'glazen deur'],
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('default-tasks.store'), $payload);

        $response->assertRedirect(route('default-tasks.index'));

        $task = DefaultTask::first();
        $this->assertNotNull($task);
        // Only l1 and l2 should be linked
        $this->assertEqualsCanonicalizing([$l1->id, $l2->id], $task->locations()->pluck('locations.id')->all());
        // door_types sanitized
        $this->assertEquals(['houten deur', 'glazen deur'], $task->door_types ?? []);
    }

    public function test_store_with_specific_locations_syncs_only_those(): void
    {
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();
        $l3 = Location::factory()->create();

        $payload = [
            'title' => 'Spot Clean',
            'description' => 'Clean spots',
            'locations' => [$l1->id, $l3->id],
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('default-tasks.store'), $payload);

        $response->assertRedirect(route('default-tasks.index'));

        $task = DefaultTask::first();
        $this->assertEqualsCanonicalizing([$l1->id, $l3->id], $task->locations()->pluck('locations.id')->all());
    }

    public function test_show_and_edit_views_render_with_expected_data(): void
    {
        $user = User::factory()->create();
        $loc = Location::factory()->create();
        $b = Benodigdheid::create(['naam' => 'Mop', 'beschrijving' => '', 'created_by' => $user->id]);

        $task = DefaultTask::create([
            'title' => 'Wipe Desks',
            'description' => 'Wipe all desks',
        ]);
        $task->locations()->sync([$loc->id]);
        $task->benodigdheden()->sync([$b->id]);

        $show = $this->actingAs($this->admin)->get(route('default-tasks.show', $task));
        $show->assertOk();
        $show->assertViewIs('default-tasks.show');
        $show->assertViewHas('defaultTask', function ($t) use ($loc) {
            return $t->locations->pluck('id')->contains($loc->id);
        });

        $edit = $this->actingAs($this->admin)->get(route('default-tasks.edit', $task));
        $edit->assertOk();
        $edit->assertViewIs('default-tasks.edit');
        $edit->assertViewHasAll(['defaultTask', 'locations', 'selectedLocations', 'benodigdheden', 'selectedBenodigdheden', 'availableDoorTypes']);
        $edit->assertViewHas('selectedLocations', function ($arr) use ($loc) {
            return in_array($loc->id, $arr, true);
        });
        $edit->assertViewHas('selectedBenodigdheden', function ($arr) use ($b) {
            return in_array($b->id, $arr, true);
        });
    }

    public function test_update_syncs_relations_and_sanitizes_door_types(): void
    {
        $user = User::factory()->create();
        $l1 = Location::factory()->create(['type_deur' => 'Houten deur']);
        $l2 = Location::factory()->create(['type_deur' => 'Glazen deur']);
        $l3 = Location::factory()->create(['type_deur' => 'Overhead deur']);
        $b1 = Benodigdheid::create(['naam' => 'Mop', 'beschrijving' => '', 'created_by' => $user->id]);
        $b2 = Benodigdheid::create(['naam' => 'Emmer', 'beschrijving' => '', 'created_by' => $user->id]);

        $task = DefaultTask::create([
            'title' => 'Initial',
            'description' => 'Initial desc',
            'applies_to_all_locations' => false,
        ]);
        $task->locations()->sync([$l3->id]);
        $task->benodigdheden()->sync([$b1->id]);

        $payload = [
            'title' => 'Updated',
            'description' => 'Updated desc',
            'applies_to_door_types' => 'on',
            'door_types' => [' HOUTEN DEUR ', 'glazen deur'],
            'benodigdheden' => [$b2->id],
        ];

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->put(route('default-tasks.update', $task), $payload);

        $response->assertRedirect(route('default-tasks.index'));

        $task->refresh();
        $this->assertEquals('Updated', $task->title);
        $this->assertEquals(['houten deur', 'glazen deur'], $task->door_types ?? []);
        // Should sync to l1 and l2 based on door types, not l3
        $this->assertEqualsCanonicalizing([$l1->id, $l2->id], $task->locations()->pluck('locations.id')->all());
        // benodigdheden synced to only b2
        $this->assertEqualsCanonicalizing([$b2->id], $task->benodigdheden()->pluck('benodigdheden.id')->all());
    }

    public function test_destroy_deletes_and_redirects(): void
    {
        $task = DefaultTask::create(['title' => 'Trash me', 'description' => '']);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->delete(route('default-tasks.destroy', $task));

        $response->assertRedirect(route('default-tasks.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('default_tasks', ['id' => $task->id]);
    }
}
