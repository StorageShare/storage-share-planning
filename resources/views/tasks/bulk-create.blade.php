<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nieuwe Taak voor Meerdere Locaties') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold">Nieuwe Taak (Meerdere Locaties)</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Maak in één keer een nieuwe taak aan voor meerdere locaties op basis van filters of selectie.</p>
                    </div>

                    <form method="POST" action="{{ route('tasks.bulk-store') }}" class="space-y-6">
                        @csrf

                        <div class="space-y-6">
                            {{-- Taak Details --}}
                            <x-form-input
                                name="title"
                                label="Titel"
                                :value="old('title')"
                                placeholder="Vul de titel van de taak in"
                                required
                            />

                            <x-form-textarea
                                name="description"
                                label="Omschrijving"
                                :value="old('description')"
                                placeholder="Beschrijf de taak in detail"
                                rows="4"
                                required
                            />

                            <x-form-input
                                name="feedback_information"
                                label="Terugkoppeling informatie"
                                :value="old('feedback_information')"
                                placeholder="Aan wie moet terugkoppeling gegeven worden?"
                            />

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <x-input-label for="priority" class="block text-sm font-medium mb-2">Prioriteit</x-input-label>
                                    <select name="priority" id="priority"
                                            class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('priority') border-red-500 @enderror">
                                        @foreach(App\Enums\TaskPriority::cases() as $priorityCase)
                                            <option value="{{ $priorityCase->value }}" {{ old('priority', App\Enums\TaskPriority::NORMAL->value) == $priorityCase->value ? 'selected' : '' }}>
                                                {{ $priorityCase->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('priority')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <x-input-label for="deadline" class="block text-sm font-medium mb-2">Deadline (optioneel)</x-input-label>
                                    <div class="relative">
                                        <x-text-input type="text" name="deadline" id="deadline" value="{{ old('deadline') }}"
                                               class="datepicker py-3 px-4 pl-11 block w-full text-sm" placeholder="Selecteer een datum" />
                                        <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 ps-4">
                                            <svg class="flex-shrink-0 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                                        </div>
                                    </div>
                                </div>
                                <x-form-input
                                    name="estimated_time_minutes"
                                    type="number"
                                    label="Geschatte tijd (minuten, optioneel)"
                                    :value="old('estimated_time_minutes', 0)"
                                    placeholder="0"
                                    min="0"
                                    max="99999"
                                />
                            </div>

                            {{-- Terugkerende taak sectie --}}
                            @anyrole('admin', 'facilities_coordinator')
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
                                <h3 class="text-md font-medium text-blue-900 dark:text-blue-100 mb-4">🔄 Terugkerende Taak (optioneel)</h3>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mb-4">
                                    Maak van deze taken terugkerende taken die automatisch opnieuw worden aangemaakt na goedkeuring.
                                </p>

                                <div class="space-y-4">
                                    <div class="flex items-center">
                                        <input id="is_recurring" name="is_recurring" type="checkbox" value="1"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
                                               {{ old('is_recurring') ? 'checked' : '' }}>
                                        <label for="is_recurring" class="ms-3 text-sm font-medium text-blue-800 dark:text-blue-200">
                                            Deze taken zijn terugkerend
                                        </label>
                                    </div>

                                    <div id="recurring-options" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="{{ old('is_recurring') ? '' : 'display: none;' }}">
                                        <div>
                                            <x-input-label for="recurring_interval_value" class="block text-sm font-medium mb-2 text-blue-800 dark:text-blue-200">Herhaal elke</x-input-label>
                                            <x-text-input type="number" name="recurring_interval_value" id="recurring_interval_value"
                                                   value="{{ old('recurring_interval_value', 1) }}"
                                                   min="1" max="365"
                                                   class="py-3 px-4 block w-full text-sm" />
                                            @error('recurring_interval_value')
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <x-input-label for="recurring_interval_type" class="block text-sm font-medium mb-2 text-blue-800 dark:text-blue-200">Tijdseenheid</x-input-label>
                                            <select name="recurring_interval_type" id="recurring_interval_type"
                                                    class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                                <option value="">Selecteer tijdseenheid</option>
                                                <option value="days" {{ old('recurring_interval_type') == 'days' ? 'selected' : '' }}>Dagen</option>
                                                <option value="weeks" {{ old('recurring_interval_type') == 'weeks' ? 'selected' : '' }}>Weken</option>
                                                <option value="months" {{ old('recurring_interval_type') == 'months' ? 'selected' : '' }}>Maanden</option>
                                                <option value="years" {{ old('recurring_interval_type') == 'years' ? 'selected' : '' }}>Jaren</option>
                                            </select>
                                            @error('recurring_interval_type')
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div id="recurring-preview" class="text-sm text-blue-600 dark:text-blue-300 font-medium" style="display: none;">
                                        <!-- Preview tekst wordt hier getoond via JavaScript -->
                                    </div>
                                </div>
                            </div>
                            @endanyrole

                            {{-- Locatie Selectie Filters --}}
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                <x-input-label class="block text-lg font-bold mb-4">Selecteer Locaties</x-input-label>

                                {{-- Alle locaties optie --}}
                                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                    <div class="flex items-center">
                                        <input id="applies_to_all_locations" name="applies_to_all_locations" type="checkbox" value="1"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
                                               {{ old('applies_to_all_locations') ? 'checked' : '' }}>
                                        <label for="applies_to_all_locations" class="ms-3 text-sm font-bold text-blue-800 dark:text-blue-200">
                                            🌍 Voor alle locaties
                                        </label>
                                    </div>
                                </div>

                                {{-- Deur types optie --}}
                                <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                                    <div class="flex items-center">
                                        <input id="applies_to_door_types" name="applies_to_door_types" type="checkbox" value="1"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-green-600 focus:ring-green-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-green-500"
                                               {{ old('applies_to_door_types') ? 'checked' : '' }}>
                                        <label for="applies_to_door_types" class="ms-3 text-sm font-bold text-green-800 dark:text-green-200">
                                            🚪 Voor locaties met specifieke deur types
                                        </label>
                                    </div>
                                </div>

                                {{-- Lift locaties optie --}}
                                <div class="mb-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                    <div class="flex items-center">
                                        <input id="applies_to_lift_locations" name="applies_to_lift_locations" type="checkbox" value="1"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-purple-600 focus:ring-purple-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-purple-500"
                                               {{ old('applies_to_lift_locations') ? 'checked' : '' }}>
                                        <label for="applies_to_lift_locations" class="ms-3 text-sm font-bold text-purple-800 dark:text-purple-200">
                                            🛗 Voor locaties met een lift
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-2 space-y-3" id="door-types-section" @if(!old('applies_to_door_types')) style="display: none;" @endif>
                                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Selecteer deur types</h4>
                                        @if(isset($availableDoorTypes) && count($availableDoorTypes) > 0)
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                @foreach($availableDoorTypes as $doorType)
                                                    <div class="flex items-center">
                                                        <input id="door_type_{{ $loop->index }}" name="door_types[]" type="checkbox" value="{{ $doorType }}"
                                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-green-600 focus:ring-green-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-green-500 door-type-checkbox"
                                                               {{ in_array($doorType, old('door_types', [])) ? 'checked' : '' }}>
                                                        <label for="door_type_{{ $loop->index }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
                                                            {{ $doorType }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Er zijn geen locaties met deur types beschikbaar.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-2 space-y-3" id="specific-locations-section">
                                    <div class="flex items-center">
                                        <input id="select_all_locations" name="select_all_locations" type="checkbox"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500">
                                        <label for="select_all_locations" class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Selecteer/Deselecteer Specifieke Locaties
                                        </label>
                                    </div>
                                    <div class="max-h-72 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md p-3 bg-white dark:bg-gray-900">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="locations_checkbox_group">
                                            @if(isset($locations) && $locations->count() > 0)
                                                @foreach($locations as $location)
                                                    <div class="flex items-center">
                                                        <input id="location_{{ $location->id }}" name="locations[]" type="checkbox" value="{{ $location->id }}"
                                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 location-checkbox"
                                                               {{ in_array($location->id, old('locations', [])) ? 'checked' : '' }}>
                                                        <label for="location_{{ $location->id }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
                                                            {{ $location->name }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            @else
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Er zijn geen locaties beschikbaar.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @error('locations')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Benodigdheden sectie --}}
                            @if(isset($requirements) && $requirements->count() > 0)
                            <div>
                                <x-input-label class="block text-sm font-medium mb-2">Benodigdheden (optioneel)</x-input-label>
                                <div class="mt-2 space-y-3">
                                    <div class="flex items-center">
                                        <input id="select_all_benodigdheden" name="select_all_benodigdheden" type="checkbox"
                                               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500">
                                        <label for="select_all_benodigdheden" class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Selecteer/Deselecteer Alles
                                        </label>
                                    </div>
                                    <div class="max-h-72 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md p-3 bg-white dark:bg-gray-900">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="benodigdheden_checkbox_group">
                                            @foreach($requirements as $requirement)
                                                <div class="flex items-center">
                                                    <input id="requirement_{{ $requirement->id }}" name="requirements[]" type="checkbox" value="{{ $requirement->id }}"
                                                           class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 requirement-checkbox"
                                                           {{ in_array($requirement->id, old('requirements', [])) ? 'checked' : '' }}>
                                                    <label for="requirement_{{ $requirement->id }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
                                                        {{ $requirement->name }}
                                                        @if($requirement->description)
                                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($requirement->description, 60) }}</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                @error('requirements')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            @endif

                            {{-- Actie einde dag sectie --}}
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Actie einde dag (optioneel)</h3>
                                <div class="space-y-4">
                                    <x-form-input
                                        name="end_day_action_title"
                                        label="Titel eindactie"
                                        :value="old('end_day_action_title')"
                                        placeholder=""
                                    />
                                    <div>
                                        <x-input-label for="end_day_action_description" class="block text-sm font-medium mb-2">Omschrijving eindactie</x-input-label>
                                        <textarea name="end_day_action_description" id="end_day_action_description" rows="3"
                                                  placeholder="Beschrijf de specifieke acties die aan het eind van de dag uitgevoerd moeten worden..."
                                                  class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('end_day_action_description') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex items-center justify-end gap-x-2">
                            <a href="{{ route('backlog.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
                                Annuleren
                            </a>
                            <x-primary-button type="submit">
                                {{ __('Taken Aanmaken') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Location selection logic
            const appliesToAllCheckbox = document.getElementById('applies_to_all_locations');
            const appliesToDoorTypesCheckbox = document.getElementById('applies_to_door_types');
            const appliesToLiftCheckbox = document.getElementById('applies_to_lift_locations');
            const specificLocationsSection = document.getElementById('specific-locations-section');
            const doorTypesSection = document.getElementById('door-types-section');
            const selectAllCheckbox = document.getElementById('select_all_locations');
            const locationCheckboxes = document.querySelectorAll('.location-checkbox');

            function toggleSections() {
                if (appliesToAllCheckbox.checked || appliesToLiftCheckbox.checked) {
                    specificLocationsSection.style.display = 'none';
                    doorTypesSection.style.display = 'none';
                } else if (appliesToDoorTypesCheckbox.checked) {
                    specificLocationsSection.style.display = 'none';
                    doorTypesSection.style.display = 'block';
                } else {
                    specificLocationsSection.style.display = 'block';
                    doorTypesSection.style.display = 'none';
                }
            }

            function handleCheckboxChange(changedCheckbox) {
                if (changedCheckbox.checked) {
                    if (changedCheckbox === appliesToAllCheckbox) {
                        appliesToDoorTypesCheckbox.checked = false;
                        appliesToLiftCheckbox.checked = false;
                    } else if (changedCheckbox === appliesToDoorTypesCheckbox) {
                        appliesToAllCheckbox.checked = false;
                        appliesToLiftCheckbox.checked = false;
                    } else if (changedCheckbox === appliesToLiftCheckbox) {
                        appliesToAllCheckbox.checked = false;
                        appliesToDoorTypesCheckbox.checked = false;
                    }
                }
                toggleSections();
            }

            appliesToAllCheckbox.addEventListener('change', () => handleCheckboxChange(appliesToAllCheckbox));
            appliesToDoorTypesCheckbox.addEventListener('change', () => handleCheckboxChange(appliesToDoorTypesCheckbox));
            appliesToLiftCheckbox.addEventListener('change', () => handleCheckboxChange(appliesToLiftCheckbox));

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    locationCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Requirements selection logic
            const selectAllBenodigdhedenCheckbox = document.getElementById('select_all_benodigdheden');
            const requirementCheckboxes = document.querySelectorAll('.requirement-checkbox');

            if (selectAllBenodigdhedenCheckbox) {
                selectAllBenodigdhedenCheckbox.addEventListener('change', function () {
                    requirementCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Recurring task logic
            const isRecurringCheckbox = document.getElementById('is_recurring');
            const recurringOptions = document.getElementById('recurring-options');
            const recurringPreview = document.getElementById('recurring-preview');
            const intervalValue = document.getElementById('recurring_interval_value');
            const intervalType = document.getElementById('recurring_interval_type');

            if (isRecurringCheckbox) {
                isRecurringCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        recurringOptions.style.display = 'grid';
                        updateRecurringPreview();
                    } else {
                        recurringOptions.style.display = 'none';
                        recurringPreview.style.display = 'none';
                    }
                });
            }

            function updateRecurringPreview() {
                const value = intervalValue ? intervalValue.value : '';
                const type = intervalType ? intervalType.value : '';

                if (value && type && value > 0) {
                    const typeLabels = {
                        'days': value == 1 ? 'dag' : 'dagen',
                        'weeks': value == 1 ? 'week' : 'weken',
                        'months': value == 1 ? 'maand' : 'maanden',
                        'years': value == 1 ? 'jaar' : 'jaren'
                    };

                    recurringPreview.textContent = `📅 Deze taken worden elke ${value} ${typeLabels[type]} automatisch opnieuw aangemaakt na goedkeuring.`;
                    recurringPreview.style.display = 'block';
                } else {
                    recurringPreview.style.display = 'none';
                }
            }

            if (intervalValue) intervalValue.addEventListener('input', updateRecurringPreview);
            if (intervalType) intervalType.addEventListener('change', updateRecurringPreview);

            // Set initial state
            toggleSections();
            if (isRecurringCheckbox && isRecurringCheckbox.checked) updateRecurringPreview();
        });
    </script>
    @endpush
</x-app-layout>
