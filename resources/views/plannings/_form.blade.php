@csrf
<div class="space-y-6">
    <div>
        <label for="planned_date" class="block text-sm font-medium mb-2 dark:text-gray-300">Geplande datum</label>
        <div class="relative">
            <input type="text" name="planned_date" id="planned_date" value="{{ old('planned_date', isset($planning) ? $planning->planned_date->format('Y-m-d') : now()->addDay()->format('Y-m-d')) }}" class="datepicker py-3 px-4 pl-11 block w-full border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('planned_date') border-red-500 @enderror" placeholder="Selecteer een datum">
            <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 ps-4">
                <svg class="flex-shrink-0 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 2v4" />
                    <path d="M16 2v4" />
                    <rect width="18" height="18" x="3" y="4" rx="2" />
                    <path d="M3 10h18" />
                </svg>
            </div>
        </div>
        @error('planned_date')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Vehicle selection --}}
    <div>
        <label for="vehicle_id" class="block text-sm font-medium mb-2 dark:text-gray-300">Voertuig</label>
        <select name="vehicle_id" id="vehicle_id" class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('vehicle_id') border-red-500 @enderror" required>
            <option value="" disabled {{ old('vehicle_id', isset($planning) ? $planning->vehicle_id : null) ? '' : 'selected' }}>Selecteer een voertuig</option>
            @foreach(($availableVehicles ?? collect()) as $vehicle)
                <option value="{{ $vehicle->id }}" {{ (string) old('vehicle_id', isset($planning) ? $planning->vehicle_id : '') === (string) $vehicle->id ? 'selected' : '' }}>
                    {{ $vehicle->name }} @if($vehicle->license_number) ({{ $vehicle->license_number }}) @endif
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">Alleen voertuigen die nog niet aan een planning op de gekozen datum zijn gekoppeld, worden hier getoond.</p>
        @error('vehicle_id')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Two Column Layout for Locations --}}
    <div class="md:grid md:grid-cols-12 md:gap-x-8 mb-6">
        {{-- Left Column: Available Locations --}}
        <div class="md:col-span-5 space-y-3">
            <div>
                <label class="block text-sm font-medium mb-2 dark:text-gray-300">Beschikbare Locaties <span class="text-xs text-gray-500 dark:text-gray-400">(klik op + om toe te voegen)</span></label>

                {{-- Filter Input --}}
                <div class="mb-3">
                    <input type="text" id="location_filter_input" placeholder="Filter locaties op naam..." class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                </div>

                @php
                $old_location_ids = collect(
                    old(
                        'location_ids',
                        isset($current_selected_location_ids)
                            ? $current_selected_location_ids
                            : (!empty($selected_location_id) ? [$selected_location_id] : [])
                    )
                )->map(fn($id) => (string)$id);
                @endphp
                <div class="space-y-3 max-h-[calc(100vh-16rem)] overflow-y-auto border border-gray-200 rounded-md p-3 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    @forelse($locations as $location_option)
                    @php
                    $location_id_str = (string)$location_option->id;
                    $priority_counts = $backlogPriorityCountsByLocation[$location_option->id] ?? [];
                    $total_time = $backlogTotalEstimatedTimeByLocation[$location_option->id] ?? 0;

                    $count_high = $priority_counts['high'] ?? ($priority_counts[App\Enums\TaskPriority::HIGH->value] ?? 0);
                    $count_normal = $priority_counts['normal'] ?? ($priority_counts[App\Enums\TaskPriority::NORMAL->value] ?? 0);
                    $count_low = $priority_counts['low'] ?? ($priority_counts[App\Enums\TaskPriority::LOW->value] ?? 0);
                    @endphp
                    <div class="location-item relative flex items-start p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors duration-150 dark:border-gray-700 dark:hover:bg-gray-800" data-location-id="{{ $location_option->id }}">
                        <div class="flex-grow">
                            <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $location_option->name }}</span>
                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 space-y-0.5">
                                <p>Open Taken:
                                    <span class="font-medium text-red-600">H:</span> {{ $count_high }},
                                    <span class="font-medium text-blue-600">N:</span> {{ $count_normal }},
                                    <span class="font-medium text-gray-500">L:</span> {{ $count_low }}
                                </p>
                                <p>Totale Tijd: <span class="font-medium">{{ $total_time }} min</span></p>
                            </div>
                        </div>
                        <div class="ml-3 flex flex-col gap-1">
                            <button type="button"
                                class="add-location-btn p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-md transition-colors duration-150 dark:text-green-400 dark:hover:text-green-300 dark:hover:bg-green-900/20"
                                data-location-id="{{ $location_option->id }}"
                                title="Toevoegen aan planning">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                        </div>
                        {{-- Hidden checkbox for form submission --}}
                        <input type="checkbox"
                            name="location_ids[]"
                            id="location_{{ $location_option->id }}"
                            value="{{ $location_option->id }}"
                            class="location-checkbox hidden"
                            {{ $old_location_ids->contains($location_id_str) ? 'checked' : '' }}>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Geen locaties beschikbaar.</p>
                    @endforelse
                </div>
                @error('location_ids')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
                @error('location_ids.*')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Right Column: Selected Locations --}}
        <div class="md:col-span-7 space-y-3 mt-6 md:mt-0">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Geselecteerde Locaties voor Planning</h3>

                <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50 shadow-sm dark:bg-gray-800 dark:border-gray-700" id="selected_locations_container">
                    <p class="text-sm text-gray-500 dark:text-gray-400" id="no_selected_locations_msg">Geen locaties geselecteerd voor deze planning.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Full Width Tasks Section --}}
    <div class="space-y-4 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Taken voor Geselecteerde Locaties</h3>

        <div class="space-y-4 max-h-96 overflow-y-auto border border-gray-200 rounded-md p-4 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700" id="tasks_by_location_container">
            <p class="text-sm text-gray-500 dark:text-gray-400">Selecteer eerst een of meerdere locaties om de bijbehorende taken te zien.</p>
        </div>
        {{-- Errors for task selection --}}
        @error('selected_default_tasks') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @error('selected_default_tasks.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @error('selected_backlog_tasks') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @error('selected_backlog_tasks.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Fields below the columns --}}
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="start_address_option" class="block text-sm font-medium mb-2 dark:text-gray-300">Startpunt</label>
                <select name="start_address_option" id="start_address_option" class="py-3 px-4 block w-full border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                    <option value="Kantoor, Isolatorweg 30 1014 AS Amsterdam" @if(old('start_address_option', $planning->start_address ?? '') == 'Kantoor, Isolatorweg 30 1014 AS Amsterdam') selected @endif>Kantoor, Isolatorweg 30 1014 AS Amsterdam</option>
                    <option value="Zuidwolde" @if(old('start_address_option', $planning->start_address ?? '') == 'Zuidwolde') selected @endif>Zuidwolde</option>
                    <option value="Gijs" @if(old('start_address_option', $planning->start_address ?? '') == 'Gijs') selected @endif>Gijs</option>
                    <option value="Anders" @if(old('start_address_option')=='Anders' || (isset($planning) && !in_array($planning->start_address, ['Kantoor, Isolatorweg 30 1014 AS Amsterdam', 'Zuidwolde', 'Gijs']))) selected @endif>Anders</option>
                </select>

                <div id="start_address_custom_wrapper" class="mt-2" style="display: none;">
                    <label for="start_address_custom" class="sr-only">Aangepast startpunt</label>
                    <input type="text" name="start_address_custom" id="start_address_custom" value="{{ old('start_address_custom', (isset($planning) && !in_array($planning->start_address, ['Kantoor, Isolatorweg 30 1014 AS Amsterdam', 'Zuidwolde', 'Gijs'])) ? $planning->start_address : '') }}" class="py-3 px-4 block w-full border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300" placeholder="Bijv. Hoofdstraat 1, Amsterdam (gebruikt voor reistijd berekening)">
                </div>
                @error('start_address')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror

                {{-- Hidden field to store the computed start address value --}}
                <input type="hidden" name="start_address" id="start_address" value="{{ old('start_address', $planning->start_address ?? 'Kantoor, Isolatorweg 30 1014 AS Amsterdam') }}">
            </div>
            <div>
                <label for="start_time" class="block text-sm font-medium mb-2 dark:text-gray-300">Starttijd</label>
                <input type="time" name="start_time" id="start_time" value="{{ old('start_time', (isset($planning) && $planning->start_time) ? \Carbon\Carbon::parse($planning->start_time)->format('H:i') : '08:00') }}" class="py-3 px-4 block w-full border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('start_time') border-red-500 @enderror">
                @error('start_time')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Volgorde Locaties</h3>
            <div id="sortable_locations_container" class="space-y-2 p-3 border border-dashed border-gray-300 rounded-md bg-gray-50 min-h-[50px] dark:bg-gray-800 dark:border-gray-600">
                {{-- Sortable location items will be injected here by JavaScript --}}
            </div>
            <input type="hidden" name="location_order" id="location_order_input">

            {{-- Travel time information --}}
            <div id="travel_times_container" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg" style="display: none;">
                <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Reistijden</h4>
                <div id="travel_times_content" class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                    {{-- Travel times will be injected here --}}
                </div>
                <div id="total_travel_time" class="mt-3 pt-3 border-t border-blue-200 dark:border-blue-700 font-medium text-blue-800 dark:text-blue-200">
                    {{-- Total travel time will be shown here --}}
                </div>
            </div>
        </div>

        {{-- Time Overview --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg border border-green-200 dark:border-green-700">
                <div class="flex items-center">
                    <svg class="w-4 h-4 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <div>
                        <p class="text-xs font-medium text-green-800 dark:text-green-200">Taken</p>
                        <p class="text-sm font-bold text-green-900 dark:text-green-100" id="total_task_time_display">0 min</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-200 dark:border-blue-700">
                <div class="flex items-center">
                    <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <div>
                        <p class="text-xs font-medium text-blue-800 dark:text-blue-200">Reistijd</p>
                        <p class="text-sm font-bold text-blue-900 dark:text-blue-100" id="total_travel_time_display">0 min</p>
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg border border-purple-200 dark:border-purple-700">
                <div class="flex items-center">
                    <svg class="w-4 h-4 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-xs font-medium text-purple-800 dark:text-purple-200">Totaal</p>
                        <p class="text-sm font-bold text-purple-900 dark:text-purple-100" id="grand_total_time_display">0 min</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium mb-2 dark:text-gray-300">Notities/instructies</label>
            <textarea name="notes" id="notes" rows="3" class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('notes') border-red-500 @enderror">{{ old('notes', $planning->notes ?? '') }}</textarea>
            @error('notes')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="users" class="block text-sm font-medium mb-2 dark:text-gray-300">Users</label>
            <select name="user_ids[]" id="user_select" multiple class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300" placeholder="Selecteer gebruikers...">
                @foreach ($users as $user)
                <option value="{{ $user->id }}" {{ (isset($planning) && $planning->users->contains($user->id)) || (is_array(old('user_ids')) && in_array($user->id, old('user_ids'))) ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div id="planning_form_script_data"
    data-locations="{{ json_encode($locations->map(fn($loc) => ['id' => $loc->id, 'name' => $loc->name])) }}"
    data-default-tasks-by-location="{{ json_encode($defaultTasksByLocation ?? []) }}"
    data-backlog-tasks-by-location="{{ json_encode($backlogTasksByLocation ?? []) }}"
    data-planned-backlog-tasks="{{ json_encode($plannedBacklogTasks ?? []) }}"
    data-plannings-show-route="{{ route('plannings.show', ['planning' => 'REPLACE_ID']) }}"
    data-backlog-priority-counts-by-location="{{ json_encode($backlogPriorityCountsByLocation ?? []) }}"
    data-backlog-total-estimated-time-by-location="{{ json_encode($backlogTotalEstimatedTimeByLocation ?? []) }}"
    data-old-selected-location-ids="{{ json_encode(old('location_ids') ?? []) }}"
    data-old-selected-default-tasks="{{ json_encode(old('selected_default_tasks') ?? []) }}"
    data-old-selected-backlog-tasks="{{ json_encode(old('selected_backlog_tasks') ?? []) }}"
    data-has-old-input="{{ old() ? 'true' : 'false' }}"
    data-current-selected-location-ids="{{ json_encode($current_selected_location_ids ?? []) }}"
    data-current-selected-default-tasks="{{ json_encode($current_selected_default_tasks ?? []) }}"
    data-current-selected-backlog-tasks="{{ json_encode($current_selected_backlog_tasks ?? []) }}"
    data-is-edit-mode="{{ isset($planning) ? 'true' : 'false' }}"
    data-initial-selected-location-ids="{{ json_encode(old('location_ids', $current_selected_location_ids ?? ($selected_location_id ? [$selected_location_id] : []) )) }}">
</div>

<div class="mt-8 flex items-center gap-x-2">
    <button type="submit" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
        {{ isset($planning) ? 'Planning Bijwerken' : 'Planning Aanmaken' }}
    </button>
    <a href="{{ route('plannings.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
        Annuleren
    </a>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const scriptDataElement = document.getElementById('planning_form_script_data');
        const tasksByLocationContainer = document.getElementById('tasks_by_location_container');
        const locationFilterInput = document.getElementById('location_filter_input');
        const locationItems = document.querySelectorAll('.location-item');
        const sortableLocationsContainer = document.getElementById('sortable_locations_container');
        const locationOrderInput = document.getElementById('location_order_input');
        const selectedLocationsContainer = document.getElementById('selected_locations_container');
        const noSelectedLocationsMsg = document.getElementById('no_selected_locations_msg');
        const plannedBacklogTasks = JSON.parse(scriptDataElement.dataset.plannedBacklogTasks || '{}');
        const planningShowRouteTemplate = scriptDataElement.dataset.planningsShowRoute;

        const allLocationsData = JSON.parse(scriptDataElement.dataset.locations);
        const defaultTasksByLocation = JSON.parse(scriptDataElement.dataset.defaultTasksByLocation);
        const backlogTasksByLocation = JSON.parse(scriptDataElement.dataset.backlogTasksByLocation);
        const backlogPriorityCountsByLocation = JSON.parse(scriptDataElement.dataset.backlogPriorityCountsByLocation);
        const backlogTotalEstimatedTimeByLocation = JSON.parse(scriptDataElement.dataset.backlogTotalEstimatedTimeByLocation);

        const oldSelectedDefaultTasks = JSON.parse(scriptDataElement.dataset.oldSelectedDefaultTasks);
        const oldSelectedBacklogTasks = JSON.parse(scriptDataElement.dataset.oldSelectedBacklogTasks);
        const hasOldInput = scriptDataElement.dataset.hasOldInput === 'true';

        const currentSelectedLocationIds = JSON.parse(scriptDataElement.dataset.currentSelectedLocationIds);
        const currentSelectedDefaultTasks = JSON.parse(scriptDataElement.dataset.currentSelectedDefaultTasks);
        const currentSelectedBacklogTasks = JSON.parse(scriptDataElement.dataset.currentSelectedBacklogTasks);
        const isEditMode = scriptDataElement.dataset.isEditMode === 'true';
        const initialSelectedLocationIds = JSON.parse(scriptDataElement.dataset.initialSelectedLocationIds).map(id => id.toString());

        window.openQuickTaskModal = function(locationId, locationName) {
            const form = document.getElementById('quick-task-form');
            if (form) {
                form.reset();
                const locationIdInput = document.getElementById('quick-task-location-id');
                const locationNameSpan = document.getElementById('quick-task-location-name');

                if (locationIdInput) {
                    locationIdInput.value = locationId;
                } else {
                    console.error('quick-task-location-id input not found');
                }

                if (locationNameSpan) {
                    locationNameSpan.textContent = locationName;
                }

                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'quick-task-modal' }));
            } else {
                console.error('Quick task form not found');
            }
        };

        const quickTaskForm = document.getElementById('quick-task-form');
        const quickTaskSubmitBtn = document.getElementById('quick-task-submit-btn');

        if (quickTaskForm) {
            quickTaskForm.addEventListener('submit', function(e) {
                e.preventDefault();
                quickTaskSubmitBtn.disabled = true;

                const formData = new FormData(quickTaskForm);
                const locationId = formData.get('location_id');

                if (!locationId) {
                    console.error('Location ID is missing from the form');
                    alert('Fout: Locatie ID ontbreekt. Probeer het opnieuw.');
                    quickTaskSubmitBtn.disabled = false;
                    return;
                }

                fetch(`/locations/${locationId}/tasks`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(async data => {
                    if (data.success) {
                        const newTask = data.task;
                        const locationId = newTask.location_id;

                        // Fetch the updated task list for this location to ensure consistency
                        try {
                            const tasksResponse = await fetch(`/locations/${locationId}/tasks`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            });
                            const tasksData = await tasksResponse.json();

                            if (tasksData.tasks) {
                                // Update our local backlogTasksByLocation with the fresh data from server
                                backlogTasksByLocation[locationId] = tasksData.tasks;

                                // Re-calculate priority counts and total time for this location
                                backlogPriorityCountsByLocation[locationId] = { high: 0, normal: 0, low: 0 };
                                let totalTime = 0;

                                tasksData.tasks.forEach(t => {
                                    const priority = t.priority.value;
                                    backlogPriorityCountsByLocation[locationId][priority] = (backlogPriorityCountsByLocation[locationId][priority] || 0) + 1;
                                    totalTime += parseInt(t.estimated_time_minutes) || 0;
                                });

                                backlogTotalEstimatedTimeByLocation[locationId] = totalTime;
                            }
                        } catch (fetchError) {
                            console.error('Error fetching updated tasks:', fetchError);
                            // Fallback if fetch fails
                            if (!backlogTasksByLocation[locationId]) {
                                backlogTasksByLocation[locationId] = [];
                            }
                            const exists = backlogTasksByLocation[locationId].some(t => t.id === newTask.id);
                            if (!exists) {
                                backlogTasksByLocation[locationId].push(newTask);
                            }
                        }

                        // Always select the newly added task
                        liveSelectedBacklogTaskIds.add(newTask.id.toString());

                        // Refresh the UI for this location item in the left column
                        updateLocationItemUI(locationId);

                        // If the location is currently selected, refresh UI
                        if (selectedLocationIds.has(locationId.toString())) {
                            refreshLocationTasksUI(locationId);
                        }

                        // Close modal
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'quick-task-modal' }));

                        // Show success message
                        showNotification(`Taak "${newTask.title}" succesvol aangemaakt.`, 'success');
                    } else {
                        alert('Er is een fout opgetreden: ' + (data.message || 'Onbekende fout'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Er is een fout opgetreden bij het opslaan van de taak.');
                })
                .finally(() => {
                    quickTaskSubmitBtn.disabled = false;
                });
            });
        }

        function updateLocationItemUI(locationId) {
            const item = document.querySelector(`.location-item[data-location-id="${locationId}"]`);
            if (!item) return;

            const counts = backlogPriorityCountsByLocation[locationId] || {};
            const totalTime = backlogTotalEstimatedTimeByLocation[locationId] || 0;

            const countsText = item.querySelector('.flex-grow p:nth-child(1)');
            if (countsText) {
                countsText.innerHTML = `Open Taken:
                    <span class="font-medium text-red-600">H:</span> ${counts.high || 0},
                    <span class="font-medium text-blue-600">N:</span> ${counts.normal || 0},
                    <span class="font-medium text-gray-500">L:</span> ${counts.low || 0}`;
            }

            const timeText = item.querySelector('.flex-grow p:nth-child(2) span');
            if (timeText) {
                timeText.textContent = `${totalTime} min`;
            }
        }

        function refreshLocationTasksUI(locationId) {
            const locationBlock = document.querySelector(`.location-tasks-block[data-location-id="${locationId}"]`);
            if (!locationBlock) return;

            const tasksContainer = locationBlock.querySelector('.location-tasks-container');
            if (!tasksContainer) return;

            // Clear and rebuild the backlog tasks section
            // We need to find the backlog section. In the current implementation, it's just appended.
            // A better way might be to re-run the whole creation for this location.
            // For now, let's find the backlog tasks header and remove everything after it for this container.

            const backlogTasks = backlogTasksByLocation[locationId] || [];

            // Re-render everything in this location's container to be safe and consistent
            tasksContainer.innerHTML = '';

            const locationDefaultTasks = defaultTasksByLocation[locationId] || [];
            createTaskSubSection(tasksContainer, 'Standaard Taken', locationDefaultTasks, 'default_tasks', liveSelectedDefaultTaskIds, locationId);
            createTaskSubSection(tasksContainer, 'Backlog Taken', backlogTasks, 'backlog_tasks', liveSelectedBacklogTaskIds, locationId);
        }

        // Set voor geselecteerde locaties
        const selectedLocationIds = new Set(initialSelectedLocationIds);

        // --- BEGINNING OF MODIFICATIONS FOR STATE PRESERVATION ---
        const liveSelectedDefaultTaskIds = new Set(
            (hasOldInput ? oldSelectedDefaultTasks : (isEditMode ? currentSelectedDefaultTasks : [])).map(id => String(id))
        );
        const uncheckedDefaultTaskIds = new Set(); // Keep track of tasks explicitly unchecked by the user
        const liveSelectedBacklogTaskIds = new Set(
            (hasOldInput ? oldSelectedBacklogTasks : (isEditMode ? currentSelectedBacklogTasks : [])).map(id => String(id))
        );
        let createModeInitialDefaultTasksAdded = false;
        // --- END OF MODIFICATIONS FOR STATE PRESERVATION ---

        if (locationFilterInput) {
            locationFilterInput.addEventListener('input', function() {
                const filterValue = this.value.toLowerCase().trim();
                locationItems.forEach(item => {
                    // The location name is inside the .flex-grow span, not inside a label
                    const locationNameElement = item.querySelector('.flex-grow > span.block.text-sm.font-semibold');
                    if (locationNameElement) {
                        const locationName = locationNameElement.textContent.toLowerCase();
                        if (locationName.includes(filterValue)) {
                            // Ensure items are shown as flex to preserve original layout
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
            });
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function createTaskSubSection(parentElement, subHeaderText, tasks, taskType, selectedTaskIdsSet, locationIdForTaskName) {
            if (!tasks || tasks.length === 0) {
                const p = document.createElement('p');
                p.className = 'text-xs text-gray-500 ps-1 py-1 dark:text-gray-400';
                p.textContent = `Geen ${subHeaderText.toLowerCase()} voor deze locatie.`;
                parentElement.appendChild(p);
                return;
            }

            const subHeader = document.createElement('h5');
            subHeader.className = 'text-sm font-semibold text-gray-700 mt-3 mb-1.5 ps-1 dark:text-gray-300';
            subHeader.textContent = subHeaderText;
            parentElement.appendChild(subHeader);

            tasks.forEach(task => {
                const div = document.createElement('div');
                let isPlannedElsewhere = false;
                let plannedInfo = null;

                if (taskType === 'backlog_tasks') {
                    plannedInfo = plannedBacklogTasks[task.id];
                    isPlannedElsewhere = !!plannedInfo;
                }

                div.className = `flex items-start ps-2 py-1.5 border-b border-gray-100 last:border-b-0 ${isPlannedElsewhere ? 'opacity-60' : 'hover:bg-gray-50 dark:hover:bg-gray-800'} rounded-sm dark:border-gray-800`;

                const taskIdName = `${taskType}_loc_${locationIdForTaskName}_task_${task.id}`;
                const taskIdIdStr = task.id.toString();
                let isChecked = selectedTaskIdsSet.has(taskIdIdStr);

                // Auto-check for default tasks if is_always_included is true and it's not already in the set (first time being added)
                // We only do this for default_tasks and only if it's not already explicitly in the set (meaning user hasn't unchecked it yet)
                // However, the set might be empty because we just started.
                // Better approach: if it's a default task AND is_always_included is true AND it's NOT in our list of explicitly unchecked tasks
                if (taskType === 'default_tasks' && task.is_always_included && !isChecked) {
                    // Check if it was previously unchecked by the user in this session
                    if (typeof uncheckedDefaultTaskIds !== 'undefined' && !uncheckedDefaultTaskIds.has(taskIdIdStr)) {
                        isChecked = true;
                        selectedTaskIdsSet.add(taskIdIdStr);
                    }
                }

                let taskEstimatedTime = parseInt(task.estimated_time_minutes) || 0;

                let timeHtml = '';
                if (taskEstimatedTime > 0) {
                    timeHtml = `<span class="ms-1 text-xs text-blue-700 font-medium dark:text-blue-400">(${taskEstimatedTime} min)</span>`;
                }

                let descriptionHtml = '';
                if (task.description) {
                    const truncatedDescription = task.description.length > 60 ?
                        task.description.substring(0, 60) + '...' :
                        task.description;
                    descriptionHtml = `<span class="inline-block text-xs text-gray-500 ps-0 mt-0.5 dark:text-gray-400 task-description-tooltip cursor-help" data-tooltip-content="${escapeHtml(task.description)}">${escapeHtml(truncatedDescription)}</span>`;
                }

                let priorityHtml = '';
                if (taskType === 'backlog_tasks' && task.priority && task.priority.label) {
                    priorityHtml = `<span class="ms-2 text-xs font-medium px-1.5 py-0.5 rounded-full ${getPriorityClass(task.priority.value)}">${escapeHtml(task.priority.label)}</span>`;
                }

                // Status en deadline informatie voor backlog taken
                let statusAndDeadlineHtml = '';
                if (taskType === 'backlog_tasks') {
                    const statusHtml = task.status ? `<span class="ms-2 text-xs font-medium px-1.5 py-0.5 rounded-full ${getStatusClass(task.status.value)}">${escapeHtml(getStatusLabel(task.status.value))}</span>` : '';
                    const deadlineHtml = task.deadline ? `<span class="ms-2 text-xs text-gray-600 dark:text-gray-400">📅 ${escapeHtml(formatDeadline(task.deadline))}</span>` : '';
                    statusAndDeadlineHtml = `${statusHtml}${deadlineHtml}`;
                }

                let plannedElsewhereHtml = '';
                if (isPlannedElsewhere) {
                    const url = planningShowRouteTemplate.replace('REPLACE_ID', plannedInfo.planning_id);
                    plannedElsewhereHtml = `
                        <span class="block text-xs text-orange-600 dark:text-orange-400 font-semibold ps-0 mt-1">
                            Al in planning: <a href="${url}" target="_blank" class="underline hover:text-orange-800 dark:hover:text-orange-200">${escapeHtml(plannedInfo.planning_title)}</a>
                        </span>`;
                }

                // If this task is planned elsewhere, ensure it's not kept in the live selected set
                if (isPlannedElsewhere && selectedTaskIdsSet.has(taskIdStr)) {
                    selectedTaskIdsSet.delete(taskIdStr);
                    isChecked = false;
                }

                div.innerHTML = `
                    <div class="flex items-start gap-2">
                        <input id="${taskIdName}" name="selected_${taskType}[]" type="checkbox" value="${task.id}" ${isChecked ? 'checked' : ''} ${isPlannedElsewhere ? 'disabled' : ''} class="shrink-0 mt-1 border-gray-300 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none task-checkbox dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500" data-estimated-time="${taskEstimatedTime}" data-location-id="${locationIdForTaskName}" data-task-type="${taskType}" data-task-id="${task.id}">
                        <label for="${taskIdName}" class="ms-1 text-sm text-gray-800 cursor-pointer flex-grow dark:text-gray-200">
                            <div class="flex items-center">
                                <span class="font-medium">${escapeHtml(task.title)}</span>
                                ${task.applies_to_all_locations ? '<span class="ml-2 px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-200">🌍 Alle locaties</span>' : ''}
                                ${timeHtml}
                                ${priorityHtml}
                                ${statusAndDeadlineHtml}
                            </div>
                            ${descriptionHtml}
                            ${plannedElsewhereHtml}
                        </label>
                        ${taskType === 'backlog_tasks' ? `<button type="button" class="edit-task-btn shrink-0 mt-1 p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors duration-150 dark:hover:bg-blue-900/20" data-task-id="${task.id}" data-task-type="${taskType}" title="Taak bewerken">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>` : ''}
                    </div>
                `;

                // Add event listener to update state and times when checkbox changes
                const checkbox = div.querySelector('input.task-checkbox');
                checkbox.addEventListener('change', function() {
                    const tType = this.dataset.taskType; // 'default_tasks' | 'backlog_tasks'
                    const tId = String(this.value);
                    if (tType === 'default_tasks') {
                        if (this.checked) {
                            liveSelectedDefaultTaskIds.add(tId);
                            if (typeof uncheckedDefaultTaskIds !== 'undefined') {
                                uncheckedDefaultTaskIds.delete(tId);
                            }
                        } else {
                            liveSelectedDefaultTaskIds.delete(tId);
                            if (typeof uncheckedDefaultTaskIds !== 'undefined') {
                                uncheckedDefaultTaskIds.add(tId);
                            }
                        }
                    } else if (tType === 'backlog_tasks') {
                        if (this.checked) {
                            liveSelectedBacklogTaskIds.add(tId);
                        } else {
                            liveSelectedBacklogTaskIds.delete(tId);
                        }
                    }
                    updateSelectedTasksTotalTime(); // This will also update location times
                });

                parentElement.appendChild(div);
            });
        }

        function populateTasks() {
            const selectedLocationIdsArray = Array.from(selectedLocationIds);

            tasksByLocationContainer.innerHTML = '';

            if (selectedLocationIdsArray.length === 0) {
                tasksByLocationContainer.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">Selecteer eerst een of meerdere locaties om de bijbehorende taken te zien.</p>';
                updateSelectedTasksTotalTime(); // Update total time even when empty
                return;
            }

            // --- MODIFICATION FOR CREATE MODE DEFAULT TASK AUTO-SELECTION ---
            if (!isEditMode && !hasOldInput && !createModeInitialDefaultTasksAdded && selectedLocationIdsArray.length > 0) {
                selectedLocationIdsArray.forEach(locId => {
                    const defaultsForThisLoc = defaultTasksByLocation[locId] || [];
                    defaultsForThisLoc.forEach(dTask => {
                        if (dTask.is_always_included) {
                            liveSelectedDefaultTaskIds.add(dTask.id.toString());
                        }
                    });
                });
                // Set the flag if we processed at least one location
                createModeInitialDefaultTasksAdded = true;
            }
            // --- END MODIFICATION ---

            selectedLocationIdsArray.forEach(locationId => {
                const locationName = getLocationNameById(locationId);

                const locationGroupDiv = document.createElement('div');
                locationGroupDiv.className = 'location-tasks-block mb-6 p-3 border border-gray-300 rounded-lg bg-gray-50 shadow dark:bg-gray-800 dark:border-gray-700'; // Styling for each location group
                locationGroupDiv.dataset.locationId = locationId;

                const locationMainHeader = document.createElement('div');
                locationMainHeader.className = 'flex justify-between items-center mb-3 border-b border-gray-300 pb-2 dark:border-gray-700';
                locationMainHeader.innerHTML = `
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">Taken voor: ${escapeHtml(locationName)}</h4>
                    <button type="button"
                        class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors duration-150 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50"
                        onclick="openQuickTaskModal(${locationId}, '${locationName.replace(/'/g, "\\'")}')"
                        title="Nieuwe taak toevoegen">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nieuwe taak
                    </button>
                `;
                locationGroupDiv.appendChild(locationMainHeader);

                // Create container for tasks to be easily refreshable
                const tasksContainer = document.createElement('div');
                tasksContainer.className = 'location-tasks-container';
                locationGroupDiv.appendChild(tasksContainer);

                // Standard Tasks
                const defaultTasksForLoc = defaultTasksByLocation[locationId] || [];
                createTaskSubSection(tasksContainer, 'Standaard Taken', defaultTasksForLoc, 'default_tasks', liveSelectedDefaultTaskIds, locationId);

                // Backlog Tasks
                const backlogTasksForLoc = backlogTasksByLocation[locationId] || [];
                createTaskSubSection(tasksContainer, 'Backlog Taken', backlogTasksForLoc, 'backlog_tasks', liveSelectedBacklogTaskIds, locationId);

                tasksByLocationContainer.appendChild(locationGroupDiv);
            });
            updateSelectedTasksTotalTime(); // Call after populating tasks
        }

        function getPriorityClass(priorityValue) {
            // Ensure priorityValue is treated as a string for matching if it's not already (e.g. from enum object)
            const priorityStr = String(priorityValue).toLowerCase();
            switch (priorityStr) {
                case 'high':
                    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                case 'normal':
                    return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                case 'low':
                    return 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                default:
                    return 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
            }
        }

        function getStatusClass(statusValue) {
            const statusStr = String(statusValue).toLowerCase();
            switch (statusStr) {
                case 'open':
                    return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                case 'in_progress':
                    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                case 'review':
                    return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                case 'completed':
                    return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                case 'rejected':
                    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                case 'skipped':
                    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                case 'closed':
                    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                default:
                    return 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
            }
        }

        function getStatusLabel(statusValue) {
            const statusStr = String(statusValue).toLowerCase();
            switch (statusStr) {
                case 'open':
                    return 'Open';
                case 'in_progress':
                    return 'In uitvoering';
                case 'review':
                    return 'Ter beoordeling';
                case 'completed':
                    return 'Voltooid';
                case 'rejected':
                    return 'Afgekeurd';
                case 'skipped':
                    return 'Overgeslagen';
                case 'closed':
                    return 'Gesloten';
                default:
                    return statusValue;
            }
        }

        function formatDeadline(deadline) {
            if (!deadline) return '';

            try {
                const date = new Date(deadline);
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                const isToday = date.toDateString() === today.toDateString();
                const isTomorrow = date.toDateString() === tomorrow.toDateString();
                const isOverdue = date < today && !isToday;

                let formattedDate = date.toLocaleDateString('nl-NL', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });

                if (isOverdue) {
                    return `⚠️ ${formattedDate} (verlopen)`;
                } else if (isToday) {
                    return `${formattedDate} (vandaag)`;
                } else if (isTomorrow) {
                    return `${formattedDate} (morgen)`;
                } else {
                    return formattedDate;
                }
            } catch (e) {
                return deadline;
            }
        }

        function getLocationNameById(locationId) {
            const foundLocation = allLocationsData.find(loc => loc.id.toString() === locationId.toString());
            return foundLocation ? foundLocation.name : 'Onbekende Locatie';
        }

        function updateSelectedTasksTotalTime() {
            let totalMinutes = 0;
            const checkedTaskCheckboxes = tasksByLocationContainer.querySelectorAll('input.task-checkbox:checked');

            checkedTaskCheckboxes.forEach(checkbox => {
                totalMinutes += parseInt(checkbox.dataset.estimatedTime) || 0;
            });

            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            let displayTime = '';

            if (hours > 0) {
                displayTime += `${hours} uur `;
            }
            if (minutes > 0 || totalMinutes === 0) { // Show '0 min' if total is zero
                displayTime += `${minutes} min`;
            }

            displayTime = displayTime.trim();
            if (displayTime === '') { // Should not happen if we show '0 min'
                displayTime = '0 min';
            }

            document.getElementById('total_task_time_display').textContent = displayTime;

            // Update location time displays in sortable list
            updateLocationTimesInSortableList();

            // Update grand total
            updateGrandTotal();
        }

        function updateLocationTimesInSortableList() {
            // Calculate time per location
            const timePerLocation = {};

            const checkedTaskCheckboxes = tasksByLocationContainer.querySelectorAll('input.task-checkbox:checked');
            checkedTaskCheckboxes.forEach(checkbox => {
                const locationId = checkbox.dataset.locationId;
                const estimatedTime = parseInt(checkbox.dataset.estimatedTime) || 0;

                if (!timePerLocation[locationId]) {
                    timePerLocation[locationId] = 0;
                }
                timePerLocation[locationId] += estimatedTime;
            });

            // Update the display in sortable list
            const sortableItems = sortableLocationsContainer.querySelectorAll('.sortable-item');
            sortableItems.forEach(item => {
                const locationId = item.dataset.locationId;
                const totalMinutes = timePerLocation[locationId] || 0;

                let timeDisplay = '';
                if (totalMinutes > 0) {
                    const hours = Math.floor(totalMinutes / 60);
                    const minutes = totalMinutes % 60;

                    if (hours > 0) {
                        timeDisplay = `${hours}u ${minutes}m`;
                    } else {
                        timeDisplay = `${minutes}m`;
                    }
                } else {
                    timeDisplay = '0m';
                }

                // Update or add time display
                let timeSpan = item.querySelector('.location-time');
                if (!timeSpan) {
                    timeSpan = document.createElement('span');
                    timeSpan.className = 'location-time ml-auto text-xs text-gray-500 dark:text-gray-400 font-medium';
                    item.appendChild(timeSpan);
                }
                timeSpan.textContent = timeDisplay;
            });
        }

        // Nieuwe functie om geselecteerde locaties te beheren
        function updateSelectedLocationsList() {
            selectedLocationsContainer.innerHTML = '';

            if (selectedLocationIds.size === 0) {
                noSelectedLocationsMsg.style.display = 'block';
                return;
            }

            noSelectedLocationsMsg.style.display = 'none';

            // Gebruik de volgorde uit sortable als die er is, anders gebruik de volgorde van toevoegen
            const currentOrder = locationOrderInput.value.split(',').filter(id => id && selectedLocationIds.has(id));
            const unorderedIds = Array.from(selectedLocationIds).filter(id => !currentOrder.includes(id));
            const orderedIds = [...currentOrder, ...unorderedIds];

            orderedIds.forEach(locationId => {
                if (selectedLocationIds.has(locationId)) {
                    const locationName = getLocationNameById(locationId);
                    const locationItem = createSelectedLocationItem(locationId, locationName);
                    selectedLocationsContainer.appendChild(locationItem);
                }
            });

            // Update hidden checkboxes
            document.querySelectorAll('input[name="location_ids[]"].location-checkbox').forEach(checkbox => {
                checkbox.checked = selectedLocationIds.has(checkbox.value);
            });

            // Update add/remove buttons visibility
            updateLocationButtonStates();
        }

        function createSelectedLocationItem(locationId, locationName) {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 bg-white border border-gray-200 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700';
            div.dataset.locationId = locationId;

            div.innerHTML = `
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">${escapeHtml(locationName)}</span>
                <button type="button"
                    class="remove-location-btn p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors duration-150 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/20"
                    data-location-id="${locationId}"
                    title="Verwijderen uit planning">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;

            return div;
        }

        function updateLocationButtonStates() {
            // Update add buttons and location appearance
            document.querySelectorAll('.location-item').forEach(item => {
                const locationId = item.dataset.locationId;
                const addBtn = item.querySelector('.add-location-btn');

                if (selectedLocationIds.has(locationId)) {
                    // Geselecteerde locatie - verberg add button en markeer als geselecteerd
                    if (addBtn) addBtn.style.display = 'none';
                    item.classList.add('opacity-60', 'bg-gray-100', 'dark:bg-gray-700');
                    item.classList.remove('hover:bg-gray-50', 'dark:hover:bg-gray-800');

                    // Voeg een "geselecteerd" indicator toe
                    if (!item.querySelector('.selected-indicator')) {
                        const indicator = document.createElement('div');
                        indicator.className = 'selected-indicator ml-3 flex items-center text-green-600 dark:text-green-400';
                        indicator.innerHTML = `
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        `;
                        const buttonContainer = item.querySelector('.ml-3');
                        if (buttonContainer) {
                            buttonContainer.appendChild(indicator);
                        }
                    }
                } else {
                    // Beschikbare locatie - toon add button en normale styling
                    if (addBtn) addBtn.style.display = 'block';
                    item.classList.remove('opacity-60', 'bg-gray-100', 'dark:bg-gray-700');
                    item.classList.add('hover:bg-gray-50', 'dark:hover:bg-gray-800');

                    // Verwijder "geselecteerd" indicator
                    const indicator = item.querySelector('.selected-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            });
        }

        function addLocationToPlanning(locationId) {
            selectedLocationIds.add(locationId);
            updateSelectedLocationsList();
            updateSortableLocationsList();
            populateTasks();

            // Reset location filter
            if (locationFilterInput) {
                locationFilterInput.value = '';
                // Show all location items
                locationItems.forEach(item => {
                    item.style.display = 'flex';
                });
            }

            // Sort available locations by distance to the newly selected location
            sortAvailableLocationsByDistance(locationId);
        }

        function removeLocationFromPlanning(locationId) {
            selectedLocationIds.delete(locationId);
            // When a location is removed, also remove tasks tied to that location from the live Sets
            try {
                const defaultsForThisLoc = defaultTasksByLocation[locationId] || [];
                defaultsForThisLoc.forEach(dTask => {
                    if (dTask && typeof dTask.id !== 'undefined') {
                        liveSelectedDefaultTaskIds.delete(String(dTask.id));
                    }
                });

                const backlogForThisLoc = backlogTasksByLocation[locationId] || [];
                backlogForThisLoc.forEach(bTask => {
                    if (bTask && typeof bTask.id !== 'undefined') {
                        liveSelectedBacklogTaskIds.delete(String(bTask.id));
                    }
                });
            } catch (e) {
                // Fail-safe: do nothing if structures are missing
            }
            updateSelectedLocationsList();
            updateSortableLocationsList();
            populateTasks();
        }

        // Database-gebaseerde afstand service
        class LocationDistanceService {
            constructor() {
                this.baseUrl = '/api/v1/location-distances';
            }

            // Haal afstand op tussen twee locaties vanuit database
            async getDistance(fromLocationId, toLocationId) {
                try {
                    const response = await fetch(`${this.baseUrl}/${fromLocationId}/to/${toLocationId}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        // Als afstand niet gevonden, return null (niet loggen als error)
                        if (response.status === 404) {
                            return null;
                        }
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    if (data.success && data.data) {
                        return {
                            distance_km: data.data.distance_km,
                            duration_minutes: data.data.duration_minutes,
                            is_recent: data.data.is_recent
                        };
                    }
                    return null;
                } catch (error) {
                    console.warn(`Could not get distance from ${fromLocationId} to ${toLocationId}:`, error);
                    return null;
                }
            }

            // Sorteer locatie IDs op afstand vanuit database
            async sortLocationsByDistance(fromLocationId, locationIds) {
                try {
                    const response = await fetch(`${this.baseUrl}/sort`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            from_location_id: fromLocationId,
                            location_ids: locationIds
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    if (data.success && data.data) {
                        return data.data.sorted_location_ids;
                    }
                    throw new Error('Invalid response format');
                } catch (error) {
                    console.error('Error sorting locations by distance:', error);
                    // Fallback: return original order
                    return locationIds;
                }
            }

            // Haal alle afstanden op vanaf een locatie (gesorteerd)
            async getDistancesFromLocation(fromLocationId) {
                try {
                    const response = await fetch(`${this.baseUrl}/${fromLocationId}/sorted`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    if (data.success && data.data) {
                        return data.data.distances;
                    }
                    return [];
                } catch (error) {
                    console.error('Error getting distances from location:', error);
                    return [];
                }
            }

            // Debug method om cache stats op te halen
            async getCacheStats() {
                try {
                    const response = await fetch(`${this.baseUrl}/stats`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    return data.success ? data.data : null;
                } catch (error) {
                    console.error('Error getting cache stats:', error);
                    return null;
                }
            }
        }

        const locationDistanceService = new LocationDistanceService();

        // Maak service beschikbaar in console voor debugging
        window.planningLocationDistanceService = locationDistanceService;

        async function sortAvailableLocationsByDistance(referencLocationId) {
            const availableLocationItems = Array.from(document.querySelectorAll('.location-item')).filter(item => {
                const locationId = item.dataset.locationId;
                return !selectedLocationIds.has(locationId) && locationId !== referencLocationId;
            });

            if (availableLocationItems.length === 0) return;

            // Voeg een loading indicator toe
            showSortingIndicator();

            try {
                // Haal locatie IDs op van beschikbare locaties
                const availableLocationIds = availableLocationItems.map(item => parseInt(item.dataset.locationId));

                // Sorteer via database service
                const sortedLocationIds = await locationDistanceService.sortLocationsByDistance(
                    parseInt(referencLocationId),
                    availableLocationIds
                );

                // Maak een map van locatie ID naar item voor snelle lookup
                const locationItemMap = new Map();
                availableLocationItems.forEach(item => {
                    locationItemMap.set(parseInt(item.dataset.locationId), item);
                });

                // Sorteer items volgens database resultaat
                const sortedItems = sortedLocationIds
                    .map(locationId => locationItemMap.get(locationId))
                    .filter(item => item); // Filter out any missing items

                // Reorganiseer de DOM - behoud geselecteerde locaties bovenaan
                const container = availableLocationItems[0].parentNode;
                const allItems = Array.from(container.children);
                const selectedItems = allItems.filter(item => selectedLocationIds.has(item.dataset.locationId));

                // Clear container
                container.innerHTML = '';

                // Voeg eerst geselecteerde locaties toe (bovenaan)
                selectedItems.forEach(item => {
                    container.appendChild(item);
                });

                // Voeg daarna beschikbare locaties toe in database-gesorteerde volgorde
                sortedItems.forEach(item => {
                    container.appendChild(item);
                });

                // Voeg eventuele overgebleven items toe (als er iets mis ging met de sortering)
                availableLocationItems.forEach(item => {
                    if (!container.contains(item)) {
                        container.appendChild(item);
                    }
                });

                hideSortingIndicator();
            } catch (error) {
                console.error('Error sorting locations by distance:', error);
                hideSortingIndicator();
            }
        }



        function showSortingIndicator() {
            // Voeg een subtiele loading indicator toe aan de locaties header
            const header = document.querySelector('.md\\:col-span-5 label');
            if (header && !header.querySelector('.sorting-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'sorting-indicator text-xs text-blue-600 ml-2';
                indicator.innerHTML = '<svg class="inline-block w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sorteren op afstand...';
                header.appendChild(indicator);
            }
        }

        function hideSortingIndicator() {
            const indicator = document.querySelector('.sorting-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        function updateTasksForSelectedLocations() {
            // Update sortable list
            updateSortableLocationsList();

            // Update tasks
            populateTasks();
        }

        // Ensure all selected tasks are submitted, even when not currently rendered
        try {
            const planningForm = tasksByLocationContainer ? tasksByLocationContainer.closest('form') : null;
            if (planningForm) {
                planningForm.addEventListener('submit', function() {
                    // Remove existing injected hidden inputs to avoid duplicates on repeated attempts
                    planningForm.querySelectorAll('.selected-task-hidden').forEach(el => el.remove());

                    // Build sets of currently checked visible inputs to avoid duplicating values
                    const visibleCheckedDefaults = new Set(Array.from(planningForm.querySelectorAll('input[name="selected_default_tasks[]"]:checked')).map(el => String(el.value)));
                    const visibleCheckedBacklogs = new Set(Array.from(planningForm.querySelectorAll('input[name="selected_backlog_tasks[]"]:checked')).map(el => String(el.value)));

                    // Inject missing default task selections
                    Array.from(liveSelectedDefaultTaskIds).forEach(id => {
                        const idStr = String(id);
                        if (!visibleCheckedDefaults.has(idStr)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_default_tasks[]';
                            input.value = idStr;
                            input.className = 'selected-task-hidden';
                            planningForm.appendChild(input);
                        }
                    });

                    // Inject missing backlog task selections
                    Array.from(liveSelectedBacklogTaskIds).forEach(id => {
                        const idStr = String(id);
                        if (!visibleCheckedBacklogs.has(idStr)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_backlog_tasks[]';
                            input.value = idStr;
                            input.className = 'selected-task-hidden';
                            planningForm.appendChild(input);
                        }
                    });
                });
            }
        } catch (e) {
            // no-op
        }

        // Event listeners voor add/remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.add-location-btn')) {
                const locationId = e.target.closest('.add-location-btn').dataset.locationId;
                addLocationToPlanning(locationId);
            } else if (e.target.closest('.remove-location-btn')) {
                const locationId = e.target.closest('.remove-location-btn').dataset.locationId;
                removeLocationFromPlanning(locationId);
            }
        });

        // Initialize SortableJS
        const sortable = new window.Sortable(sortableLocationsContainer, {
            animation: 150,
            ghostClass: 'bg-blue-100',
            onEnd: function() {
                updateLocationOrderInput();
            }
        });

        function updateLocationOrderInput() {
            const locationIds = Array.from(sortableLocationsContainer.children).map(item => item.dataset.locationId);
            locationOrderInput.value = locationIds.join(',');

            // Calculate travel times when order changes
            if (locationIds.length >= 2) {
                calculateTravelTimes(locationIds);
            } else {
                // Reset travel time display when less than 2 locations
                document.getElementById('travel_times_container').style.display = 'none';
                document.getElementById('total_travel_time_display').textContent = '0 min';
                updateGrandTotal();
            }
        }

        function calculateTravelTimes(locationIds) {
            if (locationIds.length < 2) {
                document.getElementById('travel_times_container').style.display = 'none';
                // Reset travel time display
                document.getElementById('total_travel_time_display').textContent = '0 min';
                updateGrandTotal();
                return;
            }

            // Get the start address from the hidden field
            const startAddressElement = document.getElementById('start_address');
            const startAddress = startAddressElement ? startAddressElement.value.trim() : '';

            // Debug logging
            console.log('Calculating travel times with:', {
                locationIds: locationIds,
                startAddress: startAddress,
                startAddressOption: document.getElementById('start_address_option')?.value
            });

            // Check if "Anders" is selected but no custom address is provided
            const startAddressOption = document.getElementById('start_address_option');
            if (startAddressOption && startAddressOption.value === 'Anders' && !startAddress) {
                // Show message that custom address is needed for travel time calculation
                const travelTimesContainer = document.getElementById('travel_times_container');
                const travelTimesContent = document.getElementById('travel_times_content');
                const totalTravelTime = document.getElementById('total_travel_time');

                travelTimesContainer.style.display = 'block';
                travelTimesContent.innerHTML = '<div class="flex items-center text-amber-600"><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.316 15.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>Vul een startadres in om reistijden te berekenen</div>';
                totalTravelTime.innerHTML = '';
                return;
            }

            // Show loading state
            const travelTimesContainer = document.getElementById('travel_times_container');
            const travelTimesContent = document.getElementById('travel_times_content');
            const totalTravelTime = document.getElementById('total_travel_time');

            travelTimesContainer.style.display = 'block';
            travelTimesContent.innerHTML = '<div class="flex items-center"><svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Reistijden berekenen...</div>';
            totalTravelTime.innerHTML = '';

            // Make API call
            fetch('/api/v1/travel-times/sequence', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        location_ids: locationIds.map(id => parseInt(id)),
                        start_address: startAddress || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTravelTimes(data.data);
                    } else {
                        travelTimesContent.innerHTML = '<div class="text-red-600">Fout bij berekenen reistijden</div>';
                    }
                })
                .catch(error => {
                    console.error('Error calculating travel times:', error);
                    travelTimesContent.innerHTML = '<div class="text-red-600">Fout bij berekenen reistijden</div>';
                });
        }

        function displayTravelTimes(travelData) {
            const travelTimesContent = document.getElementById('travel_times_content');
            const totalTravelTime = document.getElementById('total_travel_time');

            let html = '';

            travelData.segments.forEach((segment, index) => {
                const isReturn = segment.is_return || false;
                const iconColor = isReturn ? 'text-green-600' : 'text-blue-600';
                const returnLabel = isReturn ? ' (terug)' : '';

                html += `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 ${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                ${isReturn ?
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>' :
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>'
                                }
                            </svg>
                            <span>${segment.from} → ${segment.to}${returnLabel}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">${segment.duration_minutes} min</span>
                            ${segment.distance_km > 0 ? `<span class="text-gray-500">(${segment.distance_km} km)</span>` : ''}
                            ${segment.error ? `<span class="text-yellow-600 text-xs">(geschat)</span>` : ''}
                        </div>
                    </div>
                `;
            });

            travelTimesContent.innerHTML = html;
            totalTravelTime.innerHTML = `Totale reistijd: ${travelData.total_duration_formatted}`;

            // Update time overview displays
            const travelMinutes = travelData.total_duration_minutes;
            document.getElementById('total_travel_time_display').textContent = formatMinutes(travelMinutes);

            // Update grand total
            updateGrandTotal();
        }

        function formatMinutes(minutes) {
            if (minutes < 60) {
                return minutes + ' min';
            }
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;
            if (remainingMinutes === 0) {
                return hours + 'u';
            }
            return hours + 'u ' + remainingMinutes + 'm';
        }

        function updateGrandTotal() {
            const taskTimeText = document.getElementById('total_task_time_display').textContent;
            const travelTimeText = document.getElementById('total_travel_time_display').textContent;

            // Extract minutes from text
            const taskMinutes = extractMinutesFromText(taskTimeText);
            const travelMinutes = extractMinutesFromText(travelTimeText);
            const totalMinutes = taskMinutes + travelMinutes;

            document.getElementById('grand_total_time_display').textContent = formatMinutes(totalMinutes);
        }

        function extractMinutesFromText(text) {
            if (text.includes('u')) {
                const parts = text.split('u');
                const hours = parseInt(parts[0]) || 0;
                const minutePart = parts[1].replace('m', '').trim();
                const minutes = minutePart ? parseInt(minutePart) : 0;
                return hours * 60 + minutes;
            } else {
                return parseInt(text.replace(' min', '')) || 0;
            }
        }

        function updateSortableLocationsList() {
            sortableLocationsContainer.innerHTML = '';
            const currentOrder = locationOrderInput.value.split(',').filter(id => id && selectedLocationIds.has(id));
            const unorderedIds = Array.from(selectedLocationIds).filter(id => !currentOrder.includes(id));
            const orderedIds = [...currentOrder, ...unorderedIds];

            orderedIds.forEach(locationId => {
                if (selectedLocationIds.has(locationId)) {
                    const locationName = getLocationNameById(locationId);
                    addLocationToSortableList(locationId, locationName);
                }
            });

            updateLocationOrderInput();
        }

        function addLocationToSortableList(locationId, locationName) {
            if (document.querySelector(`#sortable_locations_container .sortable-item[data-location-id="${locationId}"]`)) {
                return; // Already exists
            }
            const div = document.createElement('div');
            div.dataset.locationId = locationId;
            div.className = 'sortable-item flex items-center p-2 bg-white border rounded-md shadow-sm cursor-grab active:cursor-grabbing dark:bg-gray-900 dark:border-gray-700';
            div.innerHTML = `
                <svg class="w-5 h-5 text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200 flex-grow">${escapeHtml(locationName)}</span>
                <span class="location-time ml-auto text-xs text-gray-500 dark:text-gray-400 font-medium">0m</span>
            `;
            sortableLocationsContainer.appendChild(div);

            // Update time display for this location
            updateLocationTimesInSortableList();
        }

        function removeLocationFromSortableList(locationId) {
            const item = document.querySelector(`#sortable_locations_container .sortable-item[data-location-id="${locationId}"]`);
            if (item) {
                item.remove();
            }
        }

        function initializeLocationSelections() {
            // Update initial state
            updateSelectedLocationsList();
            updateSortableLocationsList();
            populateTasks();

            // Calculate initial travel times if there are locations
            if (selectedLocationIds.size >= 2) {
                calculateTravelTimes(Array.from(selectedLocationIds));
            }
        }

        // Final setup on DOMContentLoaded
        initializeLocationSelections();

        const startAddressOption = document.getElementById('start_address_option');
        const customAddressWrapper = document.getElementById('start_address_custom_wrapper');
        const customAddressInput = document.getElementById('start_address_custom');

        // Listen for changes in start address to recalculate travel times
        function recalculateTravelTimesForStartAddress() {
            const sortableItems = Array.from(sortableLocationsContainer.children);
            const locationIds = sortableItems.map(item => item.dataset.locationId);
            if (locationIds.length >= 2) {
                calculateTravelTimes(locationIds);
            }
        }

        function toggleCustomAddressInput() {
            if (startAddressOption.value === 'Anders') {
                customAddressWrapper.style.display = 'block';
            } else {
                customAddressWrapper.style.display = 'none';
                customAddressInput.value = ''; // Clear the input when another option is selected
            }
            updateStartAddressHiddenField(true); // Always recalculate when changing dropdown option
        }

        function updateStartAddressHiddenField(shouldRecalculate = false) {
            const hiddenStartAddress = document.getElementById('start_address');
            if (hiddenStartAddress) {
                if (startAddressOption.value === 'Anders') {
                    // For custom address, use the custom input value (can be empty)
                    hiddenStartAddress.value = customAddressInput.value.trim();
                } else {
                    // For predefined options, use the selected value
                    hiddenStartAddress.value = startAddressOption.value;
                }
            }

            // Debug logging
            console.log('Updated hidden field:', {
                option: startAddressOption.value,
                customInput: customAddressInput.value,
                hiddenValue: hiddenStartAddress ? hiddenStartAddress.value : 'null',
                isCustom: startAddressOption.value === 'Anders',
                shouldRecalculate: shouldRecalculate
            });

            // Only recalculate if explicitly requested
            if (shouldRecalculate) {
                const isCustom = startAddressOption.value === 'Anders';
                const hasAddress = hiddenStartAddress && hiddenStartAddress.value.trim() !== '';

                if (!isCustom || hasAddress) {
                    recalculateTravelTimesForStartAddress();
                } else {
                    // For empty custom address, still trigger to show the warning message
                    recalculateTravelTimesForStartAddress();
                }
            }
        }

        startAddressOption.addEventListener('change', toggleCustomAddressInput);

        // Update hidden field during typing but don't recalculate
        customAddressInput.addEventListener('input', function() {
            updateStartAddressHiddenField(false); // Update field but don't recalculate
        });

        // Only recalculate when user leaves the field (blur) or presses Enter
        customAddressInput.addEventListener('blur', function() {
            updateStartAddressHiddenField(true); // Recalculate when leaving field
        });

        customAddressInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateStartAddressHiddenField(true); // Recalculate on Enter
            }
        });

        // Run on page load to set initial state
        toggleCustomAddressInput();
        updateStartAddressHiddenField(false); // Don't recalculate on initial load

        // Initialize Tippy.js tooltips for task descriptions
        function initializeTooltips() {
            if (typeof tippy !== 'undefined') {
                tippy('.task-description-tooltip', {
                    content(reference) {
                        return reference.getAttribute('data-tooltip-content');
                    },
                    placement: 'top',
                    theme: 'custom',
                    arrow: true,
                    delay: [300, 100],
                    maxWidth: 300,
                    interactive: false,
                    hideOnClick: true
                });
            }
        }

        // Initialize tooltips on page load
        initializeTooltips();

        // Re-initialize tooltips when tasks are added/removed dynamically
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Small delay to ensure DOM is updated
                    setTimeout(initializeTooltips, 100);
                }
            });
        });

        // Observe changes in the tasks container
        const tasksContainer = document.getElementById('tasks_by_location_container');
        if (tasksContainer) {
            observer.observe(tasksContainer, {
                childList: true,
                subtree: true
            });
        }

        // Task Edit Modal Functionality
        const taskEditModal = document.getElementById('taskEditModal');
        const closeModal = document.getElementById('closeModal');
        const cancelEdit = document.getElementById('cancelEdit');
        const taskEditForm = document.getElementById('taskEditForm');
        let currentEditingTask = null;

        // Close modal functions
        function closeTaskModal() {
            taskEditModal.classList.add('hidden');
            currentEditingTask = null;
            taskEditForm.reset();
        }

        // Event listeners for closing modal
        closeModal.addEventListener('click', closeTaskModal);
        cancelEdit.addEventListener('click', closeTaskModal);

        // Close modal when clicking outside
        taskEditModal.addEventListener('click', function(e) {
            if (e.target === taskEditModal) {
                closeTaskModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !taskEditModal.classList.contains('hidden')) {
                closeTaskModal();
            }
        });

        // Handle edit button clicks
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-task-btn')) {
                const button = e.target.closest('.edit-task-btn');
                const taskId = button.dataset.taskId;
                const taskType = button.dataset.taskType;

                if (taskType === 'backlog_tasks') {
                    openTaskEditModal(taskId);
                }
            }
        });

        function openTaskEditModal(taskId) {
            // Find the task data
            let taskData = null;
            for (const locationId in backlogTasksByLocation) {
                const tasks = backlogTasksByLocation[locationId];
                const task = tasks.find(t => t.id.toString() === taskId.toString());
                if (task) {
                    taskData = task;
                    break;
                }
            }

            if (!taskData) {
                console.error('Task not found:', taskId);
                return;
            }

            currentEditingTask = taskData;

            // Populate form fields
            document.getElementById('editTaskId').value = taskData.id;
            document.getElementById('editTaskTitle').value = taskData.title;
            document.getElementById('editTaskDescription').value = taskData.description;
            document.getElementById('editTaskPriority').value = taskData.priority.value;

            // Set status to 'open' if no status is set, otherwise use the existing status
            const statusValue = taskData.status && taskData.status.value ? taskData.status.value : 'open';
            document.getElementById('editTaskStatus').value = statusValue;

            document.getElementById('editTaskDeadline').value = taskData.deadline ? taskData.deadline.split('T')[0] : '';
            document.getElementById('editTaskEstimatedTime').value = taskData.estimated_time_minutes || '';

            // Show modal
            taskEditModal.classList.remove('hidden');
        }

        // Handle form submission
        taskEditForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(taskEditForm);
            const taskId = formData.get('task_id');

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) {
                showNotification('CSRF token niet gevonden. Ververs de pagina en probeer opnieuw.', 'error');
                return;
            }

            // Debug logging
            console.log('Updating task:', {
                taskId: taskId,
                csrfToken: csrfToken ? 'Found' : 'Missing',
                url: `/tasks/${taskId}`,
                formData: {
                    title: formData.get('title'),
                    description: formData.get('description'),
                    priority: formData.get('priority'),
                    status: formData.get('status'),
                    deadline: formData.get('deadline'),
                    estimated_time_minutes: formData.get('estimated_time_minutes')
                }
            });

            try {
                const response = await fetch(`/tasks/${taskId}`, {
                    method: 'POST', // Use POST instead of PUT for better compatibility
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        _method: 'PUT', // Laravel method spoofing
                        title: formData.get('title'),
                        description: formData.get('description'),
                        priority: formData.get('priority'),
                        status: formData.get('status'),
                        deadline: formData.get('deadline') || null,
                        estimated_time_minutes: formData.get('estimated_time_minutes') || null
                    })
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', Object.fromEntries(response.headers.entries()));

                if (response.ok) {
                    const updatedTask = await response.json();
                    console.log('Updated task data:', updatedTask);

                    // Update the task data in memory
                    if (currentEditingTask) {
                        Object.assign(currentEditingTask, {
                            title: updatedTask.title,
                            description: updatedTask.description,
                            priority: {
                                value: updatedTask.priority,
                                label: getPriorityLabel(updatedTask.priority)
                            },
                            status: updatedTask.status,
                            deadline: updatedTask.deadline,
                            estimated_time_minutes: updatedTask.estimated_time_minutes
                        });
                    }

                    // Refresh the tasks display
                    populateTasks();

                    // Close modal
                    closeTaskModal();

                    // Show success message
                    showNotification('Taak succesvol bijgewerkt!', 'success');
                } else {
                    let errorMessage = 'Onbekende fout';
                    try {
                        const errorData = await response.json();
                        errorMessage = errorData.message || errorData.error || 'Onbekende fout';
                        console.log('Error response:', errorData);
                    } catch (parseError) {
                        errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                        console.log('Parse error:', parseError);
                    }
                    showNotification('Fout bij bijwerken van taak: ' + errorMessage, 'error');
                }
            } catch (error) {
                console.error('Error updating task:', error);
                showNotification('Fout bij bijwerken van taak: ' + error.message, 'error');
            }
        });

        function getPriorityLabel(priorityValue) {
            const labels = {
                'high': 'Hoog',
                'normal': 'Normaal',
                'low': 'Laag'
            };
            return labels[priorityValue] || priorityValue;
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-md shadow-lg transition-all duration-300 transform translate-x-full ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;

            // Add to page
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    });
</script>

<!-- Tippy.js CDN -->
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>

<!-- Custom Tippy.js styling -->
<style>
    /* Light mode - lichte achtergrond */
    .tippy-box[data-theme~='custom'] {
        background-color: #f9fafb;
        color: #374151;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 12px;
        line-height: 1.4;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .tippy-box[data-theme~='custom'][data-placement^='top']>.tippy-arrow::before {
        border-top-color: #f9fafb;
    }

    .tippy-box[data-theme~='custom'][data-placement^='bottom']>.tippy-arrow::before {
        border-bottom-color: #f9fafb;
    }

    .tippy-box[data-theme~='custom'][data-placement^='left']>.tippy-arrow::before {
        border-left-color: #f9fafb;
    }

    .tippy-box[data-theme~='custom'][data-placement^='right']>.tippy-arrow::before {
        border-right-color: #f9fafb;
    }

    /* Dark mode - donkere achtergrond */
    @media (prefers-color-scheme: dark) {
        .tippy-box[data-theme~='custom'] {
            background-color: #1f2937;
            color: #f9fafb;
            border: 1px solid #374151;
        }

        .tippy-box[data-theme~='custom'][data-placement^='top']>.tippy-arrow::before {
            border-top-color: #1f2937;
        }

        .tippy-box[data-theme~='custom'][data-placement^='bottom']>.tippy-arrow::before {
            border-bottom-color: #1f2937;
        }

        .tippy-box[data-theme~='custom'][data-placement^='left']>.tippy-arrow::before {
            border-left-color: #1f2937;
        }

        .tippy-box[data-theme~='custom'][data-placement^='right']>.tippy-arrow::before {
            border-right-color: #1f2937;
        }
    }
</style>

<!-- Task Edit Modal -->
<div id="taskEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="modalTitle">Taak Bewerken</h3>
                <button type="button" id="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="taskEditForm" class="space-y-4">
                <input type="hidden" id="editTaskId" name="task_id">

                <div>
                    <label for="editTaskTitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titel</label>
                    <input type="text" id="editTaskTitle" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                </div>

                <div>
                    <label for="editTaskDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beschrijving</label>
                    <textarea id="editTaskDescription" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="editTaskPriority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioriteit</label>
                        <select id="editTaskPriority" name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option value="high">Hoog</option>
                            <option value="normal">Normaal</option>
                            <option value="low">Laag</option>
                        </select>
                    </div>

                    <div>
                        <label for="editTaskStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select id="editTaskStatus" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option value="concept">Concept</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In uitvoering</option>
                            <option value="review">Ter beoordeling</option>
                            <option value="completed">Voltooid</option>
                            <option value="rejected">Afgekeurd</option>
                            <option value="skipped">Overgeslagen</option>
                            <option value="closed">Gesloten</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="editTaskDeadline" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deadline</label>
                        <input type="date" id="editTaskDeadline" name="deadline" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    </div>

                    <div>
                        <label for="editTaskEstimatedTime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Geschatte tijd (minuten)</label>
                        <input type="number" id="editTaskEstimatedTime" name="estimated_time_minutes" min="0" max="99999" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4">
                    <button type="button" id="cancelEdit" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                        Annuleren
                    </button>
                    <button type="submit" id="saveTaskEdit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endpush
