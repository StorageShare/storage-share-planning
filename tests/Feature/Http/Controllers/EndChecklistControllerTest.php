<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Requirement;
use App\Models\EndChecklistItem;
use App\Models\Planning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EndChecklistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);
    }

    public function test_create_creates_material_and_action_items_and_replaces_existing(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();

        // Existing item that should be removed
        EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Old',
            'description' => 'Old desc',
        ]);

        // Create two materials and two end actions
        $m1 = Requirement::create(['name' => 'Bezem', 'created_by' => $user->id]);
        $m2 = Requirement::create(['name' => 'Stofzuiger', 'created_by' => $user->id]);

        $payload = [
            'materials' => [$m1->id, $m2->id],
            'end_actions' => [
                ['title' => 'Lichten uit', 'description' => 'Zet alle lichten uit'],
                ['title' => 'Alarm aan', 'description' => null],
            ],
        ];

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->postJson(route('plannings.end-checklist.create', $planning), $payload);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Old item should be deleted, total items should equal 4
        $this->assertDatabaseCount('end_checklist_items', 4);

        // Verify items belong to planning and contain the end actions
        $this->assertDatabaseHas('end_checklist_items', [
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Lichten uit',
        ]);
        $this->assertDatabaseHas('end_checklist_items', [
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Alarm aan',
        ]);
    }

    public function test_upload_photo_stores_file_and_updates_item(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Maak foto',
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 600, 600);

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->postJson(route('end-checklist-items.upload-photo', $item), [
                'photo' => $file,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $item->refresh();
        $this->assertNotNull($item->photo_path);
        Storage::disk('public')->assertExists($item->photo_path);
        $this->assertEquals($user->id, $item->uploaded_by);
        $this->assertNotNull($item->uploaded_at);
    }

    public function test_delete_photo_deletes_file_and_clears_fields(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Foto verwijderen',
        ]);

        // Seed a stored file
        $path = UploadedFile::fake()->image('to-delete.jpg')->store('end-checklist-photos', 'public');
        $item->update([
            'photo_path' => $path,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->deleteJson(route('end-checklist-items.delete-photo', $item));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $item->refresh();
        $this->assertNull($item->photo_path);
        $this->assertNull($item->uploaded_by);
        $this->assertNull($item->uploaded_at);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_submit_requires_all_photos_and_sets_pending_when_complete(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();

        $i1 = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'A',
            'photo_path' => null,
        ]);
        $i2 = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'B',
        ]);
        $i2->photos()->create([
            'file_path' => 'some/path.jpg',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);

        // Should fail because one item has no photo
        $response = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->postJson(route('plannings.end-checklist.submit', $planning));
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        // Fix: add photo for the missing one
        $i1->photos()->create([
            'file_path' => 'another/path.jpg',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);

        $response2 = $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->postJson(route('plannings.end-checklist.submit', $planning));
        $response2->assertOk();
        $response2->assertJson(['success' => true]);

        // All items should be set to pending and admin fields cleared
        $this->assertDatabaseHas('end_checklist_items', [
            'id' => $i1->id,
            'status' => 'pending',
            'admin_notes' => null,
        ]);
        $this->assertDatabaseHas('end_checklist_items', [
            'id' => $i2->id,
            'status' => 'pending',
            'admin_notes' => null,
        ]);
    }

    public function test_index_returns_items_and_flags(): void
    {
        $user = User::factory()->create(['role' => Role::ALGEMEEN_MEDEWERKER->value]);
        $planning = Planning::factory()->create();

        EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'No photo',
            'photo_path' => null,
        ]);
        EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'With photo',
            'photo_path' => 'p.jpg',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('plannings.end-checklist.index', $planning));

        $response->assertOk();
        $response->assertJsonStructure([
            'items', 'has_submitted', 'is_approved'
        ]);

        // has_submitted is false because one missing photo
        $response->assertJson(['has_submitted' => false]);
        $response->assertJson(['is_approved' => false]);
    }

    public function test_admin_can_review_item_and_update_status(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Review me',
            'status' => 'pending',
            'photo_path' => 'x.jpg',
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->postJson(route('admin.end-checklist-items.review', $item), [
                'status' => 'approved',
                'admin_notes' => 'ok',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('end_checklist_items', [
            'id' => $item->id,
            'status' => 'approved',
            'admin_notes' => 'ok',
        ]);
    }

    public function test_pending_reviews_returns_plannings_with_pending_items_with_photos(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $p1 = Planning::factory()->create();
        $p2 = Planning::factory()->create();

        // p1 has a pending item with photo => should be included
        EndChecklistItem::create([
            'planning_id' => $p1->id,
            'type' => 'end_action',
            'title' => 'X',
            'status' => 'pending',
        ]);
        EndChecklistItem::where('planning_id', $p1->id)->first()->photos()->create([
            'file_path' => 'f.jpg',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
        ]);
        // p2 has pending without photo => should be excluded
        EndChecklistItem::create([
            'planning_id' => $p2->id,
            'type' => 'end_action',
            'title' => 'Y',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.end-checklists.pending'));

        $response->assertOk();
        $response->assertJsonStructure(['plannings']);

        $planningIds = collect($response->json('plannings'))->pluck('id');
        $this->assertTrue($planningIds->contains($p1->id));
        $this->assertFalse($planningIds->contains($p2->id));
    }

    public function test_admin_approve_item_approves_related_and_redirects_with_flash(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();

        $itemA = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Deur sluiten',
            'status' => 'pending',
        ]);
        // Related by same title and type
        $itemA2 = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Deur sluiten',
            'status' => 'pending',
        ]);
        // Different title => not affected
        EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Anders',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('admin.end-checklist.approve', $itemA));

        $response->assertRedirect(route('admin.tasks.review'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('end_checklist_items', [
            'id' => $itemA->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('end_checklist_items', [
            'id' => $itemA2->id,
            'status' => 'approved',
        ]);
    }

    public function test_show_reject_form_renders_view(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Z',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.end-checklist.reject', $item));

        $response->assertOk();
        $response->assertViewIs('admin.end-checklist.reject');
        $response->assertViewHas('item');
    }

    public function test_reject_item_requires_notes_and_can_redirect_to_task_create_with_prefill(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN->value]);
        $planning = Planning::factory()->create();
        $location = \App\Models\Location::factory()->create();
        $planning->locations()->attach($location->id, ['sort_order' => 0]);
        $item = EndChecklistItem::create([
            'planning_id' => $planning->id,
            'type' => 'end_action',
            'title' => 'Niet goed',
            'status' => 'pending',
        ]);

        // Missing admin_notes should fail
        $response = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('admin.end-checklist.reject.process', $item), [
                // 'admin_notes' omitted
            ]);
        $response->assertSessionHasErrors(['admin_notes']);

        // With create_new_task=true => redirect to locations.tasks.create with prefill
        $response2 = $this->actingAs($admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('admin.end-checklist.reject.process', $item), [
                'admin_notes' => 'reden',
                'create_new_task' => true,
            ]);

        $response2->assertRedirect();
        $this->assertTrue(session()->has('prefill'));
        $prefill = session('prefill');
        $this->assertEquals('Niet goed', $prefill['title']);
        $this->assertStringContainsString('Afwijzingsreden: reden', $prefill['description']);
    }
}
