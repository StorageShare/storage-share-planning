### End Checklist Items — Concept, Lifecycle, and Relation to Plannings and Tasks

This document explains what End Checklist Items are, why they exist, and how they relate to Plannings, Locations, Requirements (materials), and Tasks in this application.

#### What are End Checklist Items?
End Checklist Items are the required proof points at the end of a workday or after completing a planning. They ensure that:
- All materials that were taken for tasks are returned in good order (type `material`).
- All end-of-day actions that must be performed at certain locations are completed (type `end_action`).

Every item requires a photo to be uploaded as evidence. After submission, an administrator reviews each item and approves or rejects it.

#### Where are they used in the app?
- Mobile/user-facing flow: displayed in the "My Planning" view once the end-checklist step is reached. Users upload photos for each item and submit the full checklist for review.
- Admin flow: accessed via the admin section to review submissions item-by-item and approve or reject them with optional notes.

#### Data Model Overview
- `Planning` — the central entity representing the planned workday or route.
  - Relation: `planning->endChecklistItems()` (HasMany)
  - Methods:
    - `hasSubmittedEndChecklist()` — true when every item has a `photo_path`.
    - `hasApprovedEndChecklist()` — true when every item has `status = approved`.
    - `checkAndUpdateStatus()` — updates planning `status` based on task completion and end checklist state.

- `EndChecklistItem` — represents one checklist requirement to be proven at the end.
  - Key attributes (see `app/Models/EndChecklistItem.php`):
    - `planning_id` — owning planning
    - `location_id` — related location where the item belongs (derived; see below)
    - `type` — `material` or `end_action`
    - `requirement_id` — the `Requirement` (material) for `material` items
    - `title`, `description` — human-readable details
    - `photo_path`, `uploaded_by`, `uploaded_at` — evidence metadata
    - `status` — lifecycle status: `open` (initial), `pending` (submitted), `approved`, `rejected`
    - `admin_notes`, `reviewed_at`, `reviewed_by` — review metadata
  - Helpers and scopes:
    - `materials()`, `endActions()` scopes; `isOpen()`, `isPending()`, `isApproved()`, `isRejected()` helpers

- `Requirement` — a material that tasks may require. Referenced by `requirement_id` for `material` items.

- `Location` — the physical site; `EndChecklistItem.location_id` ties items to where they belong.

#### How are End Checklist Items created?
Endpoint: `EndChecklistController@create`
- Input contains two arrays:
  - `materials`: array of `Requirement` IDs that must be returned.
  - `end_actions`: array of objects `{ title, description? }` describing end-of-day actions.
- Behavior:
  - Existing end checklist items for the planning are deleted and recreated from the request.
  - For each `materials[]` entry, an item of type `material` is created. The item’s title is based on the `Requirement` name.
  - For each `end_actions[]` entry, an item of type `end_action` is created.

Location linking (backfill):
- Command `checklist:update-locations` (`app/Console/Commands/UpdateEndChecklistLocations.php`) attempts to link each item to a `location_id` by finding the `PlanningTask` that references the same `Requirement` (for `material`) or the same `end_day_action_title` (for `end_action`). If that task is associated with a specific location, that location is saved on the item.

#### User Submission Flow
1. Users upload a photo per item using `EndChecklistController@uploadPhoto`.
   - Replaces any previous photo.
   - Saves `photo_path`, `uploaded_by`, `uploaded_at`.
2. When all items have photos, users call `EndChecklistController@submit`.
   - Validates that there are no items without `photo_path`.
   - Sets every item’s `status` to `pending` (from initial `open`) and clears previous `admin_notes`, `reviewed_*` fields.
   - Triggers `planning->checkAndUpdateStatus()`.

#### Admin Review Flow
- Admin views pending items and reviews per item via `EndChecklistController@review`, or uses convenience endpoints `approveItem` / `rejectItem`.
- On review:
  - Sets `status` to `approved` or `rejected`.
  - Optionally fills `admin_notes`.
  - Sets `reviewed_at`, `reviewed_by`.
  - Calls `planning->checkAndUpdateStatus()` to re-evaluate the planning state.

#### How End Checklist relates to Plannings and Tasks
- Tasks drive what should be in the end checklist:
  - If a task (or default task) requires certain `Requirements` (materials), matching `material` items will be present in the checklist to ensure return of those materials.
  - If a task defines an `end_day_action_title`, a corresponding `end_action` item can be created and linked to that task’s `Location`.
- Planning status lifecycle (see `Planning::checkAndUpdateStatus()`):
  - While tasks are incomplete, planning stays `in_progress` (or `open`).
  - When all `planningTasks` are completed, the system checks the end checklist:
    - If all end checklist items are `approved`, planning becomes `completed`.
    - If tasks are completed but end checklist is not fully approved yet, planning becomes `pending_end_checklist`.
  - If later a task is marked incomplete again, planning state is moved back from `completed`/`pending_end_checklist` to `in_progress`.

In short: finishing tasks is necessary but not sufficient; the end checklist must also be submitted and approved to truly complete the planning.

#### Status Definitions and Transitions
- Item `status`:
  - `open`: default state upon creation; user can upload photos; not yet submitted for review.
  - `pending`: awaiting admin review (set on submission or re-submission).
  - `approved`: accepted by admin.
  - `rejected`: not accepted; user should re-upload evidence and re-submit.
- Planning `status` (selected states relevant here):
  - `in_progress`/`open`: not all tasks complete.
  - `pending_end_checklist`: tasks complete, but end checklist not fully approved.
  - `completed`: tasks complete AND end checklist fully approved.

#### API Summary (selected)
- `POST /plannings/{planning}/end-checklist` → create items (`create`)
- `GET /plannings/{planning}/end-checklist` → list items and flags (`index`)
- `POST /end-checklist/items/{item}/photo` → upload photo (`uploadPhoto`)
- `DELETE /end-checklist/items/{item}/photo` → delete photo (`deletePhoto`)
- `POST /plannings/{planning}/end-checklist/submit` → submit all items (`submit`)
- `POST /end-checklist/items/{item}/review` → admin review item (`review`)
- Optional convenience endpoints: `approveItem`, `rejectItem`

Note: actual route URIs may differ; consult the routes file for the exact paths.

#### UI Notes (My Planning view)
- The end checklist becomes visible on the final step of the planning’s workflow.
- Users see progress counters (e.g., number of items with photos) and can only submit when all items have a photo.
- After submission, badges indicate whether the checklist is submitted and/or fully approved.

#### Operational Notes
- Re-submission: If an item gets rejected, users can update the photo and re-submit the entire checklist; all items are set to `pending` on submission.
- Storage: Photos are stored on the `public` disk under `end-checklist-photos`.
- Cleanup/Maintenance: A command exists to backfill `location_id` for historical items and another to deduplicate items if needed.

#### Quick Pointers to Code
- Model: `app/Models/EndChecklistItem.php`
- Controller: `app/Http/Controllers/EndChecklistController.php`
- Planning model (status logic): `app/Models/Planning.php`
- Location backfill command: `app/Console/Commands/UpdateEndChecklistLocations.php`
