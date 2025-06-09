@csrf
<div class="space-y-6">

    {{-- Two Column Layout --}}
    <div class="md:grid md:grid-cols-12 md:gap-x-8">
        {{-- Left Column: Locations --}}
        <div class="md:col-span-5 space-y-3">
            <div>
                <label class="block text-sm font-medium mb-2 dark:text-gray-300">Locatie(s) <span class="text-xs text-gray-500 dark:text-gray-400">(meerdere selecteren mogelijk)</span></label>
                
                {{-- Filter Input --}}
                <div class="mb-3">
                    <input type="text" id="location_filter_input" placeholder="Filter locaties op naam..." class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                </div>

                @php
                    $old_location_ids = collect(old('location_ids', $current_selected_location_ids ?? ($selected_location_id ? [$selected_location_id] : []) ))->map(fn($id) => (string)$id);
                @endphp
                <div class="space-y-3 max-h-[calc(100vh-20rem)] overflow-y-auto border border-gray-200 rounded-md p-3 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    @forelse($locations as $location_option)
                        @php
                            $location_id_str = (string)$location_option->id;
                            $priority_counts = $backlogPriorityCountsByLocation[$location_option->id] ?? [];
                            $total_time = $backlogTotalEstimatedTimeByLocation[$location_option->id] ?? 0;
                            
                            $count_high = $priority_counts['high'] ?? ($priority_counts[App\Enums\TaskPriority::HIGH->value] ?? 0);
                            $count_normal = $priority_counts['normal'] ?? ($priority_counts[App\Enums\TaskPriority::NORMAL->value] ?? 0);
                            $count_low = $priority_counts['low'] ?? ($priority_counts[App\Enums\TaskPriority::LOW->value] ?? 0);
                        @endphp
                        <div class="location-item relative flex items-start p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors duration-150 dark:border-gray-700 dark:hover:bg-gray-800">
                            <div class="flex items-center h-5 mt-1">
                                <input type="checkbox"
                                       name="location_ids[]"
                                       id="location_{{ $location_option->id }}"
                                       value="{{ $location_option->id }}"
                                       class="location-checkbox shrink-0 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
                                       {{ $old_location_ids->contains($location_id_str) ? 'checked' : '' }}>
                            </div>
                            <label for="location_{{ $location_option->id }}" class="ms-3 flex-grow cursor-pointer">
                                <span class="block text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $location_option->name }}</span>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 space-y-0.5">
                                    <p>Open Taken:
                                        <span class="font-medium text-red-600">H:</span> {{ $count_high }},
                                        <span class="font-medium text-blue-600">N:</span> {{ $count_normal }},
                                        <span class="font-medium text-gray-500">L:</span> {{ $count_low }}
                                    </p>
                                    <p>Totale Tijd: <span class="font-medium">{{ $total_time }} min</span></p>
                                </div>
                            </label>
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

        {{-- Right Column: Tasks by Location --}}
        <div class="md:col-span-7 space-y-4 mt-6 md:mt-0">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Geselecteerde Taken</h3>
            <div class="mb-3 p-3 border border-dashed border-blue-300 bg-blue-50 rounded-md dark:bg-blue-900/20 dark:border-blue-700">
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Totaal Geselecteerde Tijd:</span>
                <span id="total_selected_time_display" class="text-sm font-bold text-blue-800 dark:text-blue-200">0 min</span>
            </div>
            <div class="space-y-4 max-h-[calc(100vh-22rem)] overflow-y-auto border border-gray-200 rounded-md p-4 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700" id="tasks_by_location_container">
                <p class="text-sm text-gray-500 dark:text-gray-400">Selecteer eerst een of meerdere locaties om de bijbehorende taken te zien.</p>
            </div>
            {{-- Errors for task selection --}}
            @error('selected_default_tasks') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('selected_default_tasks.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('selected_backlog_tasks') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('selected_backlog_tasks.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Fields below the columns --}}
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 space-y-5">
        <div>
            <label for="planned_date" class="block text-sm font-medium mb-2 dark:text-gray-300">Geplande datum</label>
            <input type="date" name="planned_date" id="planned_date" value="{{ old('planned_date', isset($planning) ? $planning->planned_date->format('Y-m-d') : now()->format('Y-m-d')) }}" class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('planned_date') border-red-500 @enderror">
            @error('planned_date')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium mb-2 dark:text-gray-300">Notities</label>
            <textarea name="notes" id="notes" rows="3" class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 @error('notes') border-red-500 @enderror">{{ old('notes', $planning->notes ?? '') }}</textarea>
            @error('notes')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="users" class="block text-sm font-medium mb-2 dark:text-gray-300">Users</label>
            <select name="user_ids[]" id="users" multiple class="py-3 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
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
    document.addEventListener('DOMContentLoaded', function () {
        const scriptDataElement = document.getElementById('planning_form_script_data');
        const locationCheckboxes = document.querySelectorAll('input[name="location_ids[]"].location-checkbox');
        const tasksByLocationContainer = document.getElementById('tasks_by_location_container');
        const locationFilterInput = document.getElementById('location_filter_input');
        const locationItems = document.querySelectorAll('.location-item');

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
        
        // --- BEGINNING OF MODIFICATIONS FOR STATE PRESERVATION ---
        const liveSelectedDefaultTaskIds = new Set(
            (hasOldInput ? oldSelectedDefaultTasks : (isEditMode ? currentSelectedDefaultTasks : [])).map(id => String(id))
        );
        const liveSelectedBacklogTaskIds = new Set(
            (hasOldInput ? oldSelectedBacklogTasks : (isEditMode ? currentSelectedBacklogTasks : [])).map(id => String(id))
        );
        let createModeInitialDefaultTasksAdded = false;
        // --- END OF MODIFICATIONS FOR STATE PRESERVATION ---
        
        if (locationFilterInput) {
            locationFilterInput.addEventListener('input', function () {
                const filterValue = this.value.toLowerCase().trim();
                locationItems.forEach(item => {
                    const locationNameElement = item.querySelector('label > span.block.text-sm.font-semibold');
                    if (locationNameElement) {
                        const locationName = locationNameElement.textContent.toLowerCase();
                        if (locationName.includes(filterValue)) {
                            item.style.display = ''; // Or 'flex' if it was originally display:flex
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
                div.className = 'flex items-start ps-2 py-1.5 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 rounded-sm dark:border-gray-800 dark:hover:bg-gray-800'; // items-start for multi-line label
                
                const taskIdName = `${taskType}_loc_${locationIdForTaskName}_task_${task.id}`;
                
                const taskIdStr = task.id.toString();
                let isChecked = selectedTaskIdsSet.has(taskIdStr);

                let taskEstimatedTime = parseInt(task.estimated_time_minutes) || 0;

                let timeHtml = '';
                if (taskEstimatedTime > 0) {
                    timeHtml = `<span class="ms-1 text-xs text-blue-700 font-medium dark:text-blue-400">(${taskEstimatedTime} min)</span>`;
                }

                let descriptionHtml = '';
                if (task.description) {
                    descriptionHtml = `<span class="block text-xs text-gray-500 ps-0 mt-0.5 dark:text-gray-400">${escapeHtml(task.description)}</span>`;
                }

                let priorityHtml = '';
                if (taskType === 'backlog_tasks' && task.priority && task.priority.label) {
                    priorityHtml = `<span class="ms-2 text-xs font-medium px-1.5 py-0.5 rounded-full ${getPriorityClass(task.priority.value)}">${escapeHtml(task.priority.label)}</span>`;
                }

                div.innerHTML = `
                    <input id="${taskIdName}" name="selected_${taskType}[]" type="checkbox" value="${task.id}" ${isChecked ? 'checked' : ''} class="shrink-0 mt-1 border-gray-300 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none task-checkbox dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500" data-estimated-time="${taskEstimatedTime}">
                    <label for="${taskIdName}" class="ms-3 text-sm text-gray-800 cursor-pointer flex-grow dark:text-gray-200">
                        <div class="flex items-center">
                            <span class="font-medium">${escapeHtml(task.title)}</span>
                            ${timeHtml}
                            ${priorityHtml}
                        </div>
                        ${descriptionHtml}
                    </label>
                `;
                parentElement.appendChild(div);
            });
        }

        function populateTasks() {
            const selectedLocationIds = Array.from(locationCheckboxes)
                                            .filter(checkbox => checkbox.checked)
                                            .map(checkbox => checkbox.value);

            tasksByLocationContainer.innerHTML = ''; 
            
            if (selectedLocationIds.length === 0) {
                tasksByLocationContainer.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">Selecteer eerst een of meerdere locaties om de bijbehorende taken te zien.</p>';
                updateSelectedTasksTotalTime(); // Update total time even when empty
                return;
            }

            // --- MODIFICATION FOR CREATE MODE DEFAULT TASK AUTO-SELECTION ---
            if (!isEditMode && !hasOldInput && !createModeInitialDefaultTasksAdded && selectedLocationIds.length > 0) {
                selectedLocationIds.forEach(locId => {
                    const defaultsForThisLoc = defaultTasksByLocation[locId] || [];
                    defaultsForThisLoc.forEach(dTask => {
                        liveSelectedDefaultTaskIds.add(dTask.id.toString());
                    });
                });
                // Set the flag only if there were actually locations and potential defaults to add.
                // This ensures if the first selection is a location with no default tasks, the flag isn't prematurely set.
                if (selectedLocationIds.some(locId => (defaultTasksByLocation[locId] || []).length > 0)) {
                    createModeInitialDefaultTasksAdded = true;
                }
            }
            // --- END MODIFICATION ---

            selectedLocationIds.forEach(locationId => {
                const locationName = getLocationNameById(locationId);

                const locationGroupDiv = document.createElement('div');
                locationGroupDiv.className = 'mb-6 p-3 border border-gray-300 rounded-lg bg-gray-50 shadow dark:bg-gray-800 dark:border-gray-700'; // Styling for each location group

                const locationMainHeader = document.createElement('h4');
                locationMainHeader.className = 'text-base font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2 dark:text-gray-200 dark:border-gray-700';
                locationMainHeader.textContent = `Taken voor: ${escapeHtml(locationName)}`;
                locationGroupDiv.appendChild(locationMainHeader);

                // Standard Tasks
                const defaultTasksForLoc = defaultTasksByLocation[locationId] || [];
                createTaskSubSection(locationGroupDiv, 'Standaard Taken', defaultTasksForLoc, 'default_tasks', liveSelectedDefaultTaskIds, locationId);

                // Backlog Tasks
                const backlogTasksForLoc = backlogTasksByLocation[locationId] || [];
                createTaskSubSection(locationGroupDiv, 'Backlog Taken', backlogTasksForLoc, 'backlog_tasks', liveSelectedBacklogTaskIds, locationId);

                tasksByLocationContainer.appendChild(locationGroupDiv);
            });
            updateSelectedTasksTotalTime(); // Call after populating tasks
        }
        
        function getPriorityClass(priorityValue) {
            // Ensure priorityValue is treated as a string for matching if it's not already (e.g. from enum object)
            const priorityStr = String(priorityValue).toLowerCase();
            switch (priorityStr) {
                case 'high': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                case 'normal': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                case 'low': return 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                default: return 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
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

            document.getElementById('total_selected_time_display').textContent = displayTime;
        }

        locationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                populateTasks(); // This will call updateSelectedTasksTotalTime() at the end
                if (locationFilterInput && this.checked) {
                    // locationFilterInput.value = ''; // Clear filter on select (optional)
                    // locationFilterInput.dispatchEvent(new Event('input')); // Trigger filter update
                }
            });
        });

        // Event delegation for dynamically added task checkboxes
        tasksByLocationContainer.addEventListener('change', function(event) {
            if (event.target.classList.contains('task-checkbox')) {
                const taskId = event.target.value;
                const isChecked = event.target.checked;
                const taskNameAttr = event.target.name; // e.g., "selected_default_tasks[]"

                if (taskNameAttr.includes('default_tasks')) {
                    isChecked ? liveSelectedDefaultTaskIds.add(taskId) : liveSelectedDefaultTaskIds.delete(taskId);
                } else if (taskNameAttr.includes('backlog_tasks')) {
                    isChecked ? liveSelectedBacklogTaskIds.add(taskId) : liveSelectedBacklogTaskIds.delete(taskId);
                }
                updateSelectedTasksTotalTime();
            }
        });

        // Initial population of tasks and total time
        populateTasks();
    });
</script>
@endpush 