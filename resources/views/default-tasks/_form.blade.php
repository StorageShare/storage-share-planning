<div class="space-y-6">
    <x-form-input 
        name="title" 
        label="Titel" 
        :value="$defaultTask->title ?? ''"
        placeholder="Vul de titel van de standaardtaak in" 
        required 
    />

    <x-form-textarea 
        name="description" 
        label="Omschrijving" 
        :value="$defaultTask->description ?? ''"
        placeholder="Beschrijf de standaardtaak in detail"
        rows="4"
        required
    />

    <x-form-input 
        name="estimated_time_minutes" 
        type="number"
        label="Geschatte tijd (minuten)" 
        :value="$defaultTask->estimated_time_minutes ?? ''"
        placeholder="0"
        min="0"
        max="99999"
    />

    <div>
        <x-input-label class="block text-sm font-medium mb-2">Locaties</x-input-label>
        
        {{-- Alle locaties optie --}}
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-center">
                <input id="applies_to_all_locations" name="applies_to_all_locations" type="checkbox" value="1"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
                       {{ old('applies_to_all_locations', $defaultTask->applies_to_all_locations ?? false) ? 'checked' : '' }}>
                <label for="applies_to_all_locations" class="ms-3 text-sm font-bold text-blue-800 dark:text-blue-200">
                    🌍 Van toepassing op alle locaties (inclusief toekomstige locaties)
                </label>
            </div>
            <p class="text-xs text-blue-600 dark:text-blue-300 mt-2 ml-8">
                Wanneer deze optie is geselecteerd, wordt deze standaard taak automatisch beschikbaar voor alle huidige en toekomstige locaties.
            </p>
        </div>

        {{-- Deur types optie --}}
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
            <div class="flex items-center">
                <input id="applies_to_door_types" name="applies_to_door_types" type="checkbox" value="1"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-green-600 focus:ring-green-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-green-500"
                       {{ old('applies_to_door_types', $defaultTask->applies_to_door_types ?? false) ? 'checked' : '' }}>
                <label for="applies_to_door_types" class="ms-3 text-sm font-bold text-green-800 dark:text-green-200">
                    🚪 Van toepassing op locaties met specifieke deur types
                </label>
            </div>
            <p class="text-xs text-green-600 dark:text-green-300 mt-2 ml-8">
                Wanneer deze optie is geselecteerd, wordt deze standaard taak automatisch beschikbaar voor alle huidige en toekomstige locaties met de geselecteerde deur types (hoofdletter ongevoelig).
            </p>
        </div>

        {{-- Lift locaties optie --}}
        <div class="mb-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
            <div class="flex items-center">
                <input id="applies_to_lift_locations" name="applies_to_lift_locations" type="checkbox" value="1"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-purple-600 focus:ring-purple-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-purple-500"
                       {{ old('applies_to_lift_locations', $defaultTask->applies_to_lift_locations ?? false) ? 'checked' : '' }}>
                <label for="applies_to_lift_locations" class="ms-3 text-sm font-bold text-purple-800 dark:text-purple-200">
                    🛗 Van toepassing op locaties met een lift
                </label>
            </div>
            <p class="text-xs text-purple-600 dark:text-purple-300 mt-2 ml-8">
                Wanneer deze optie is geselecteerd, wordt deze standaard taak automatisch beschikbaar voor alle huidige en toekomstige locaties waar lift informatie is ingevuld.
            </p>
        </div>

        <div class="mt-2 space-y-3" id="door-types-section" @if(!old('applies_to_door_types', $defaultTask->applies_to_door_types ?? false)) style="display: none;" @endif>
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border">
                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Selecteer deur types</h4>
                @if(isset($availableDoorTypes) && count($availableDoorTypes) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($availableDoorTypes as $doorType)
                            <div class="flex items-center">
                                <input id="door_type_{{ $loop->index }}" name="door_types[]" type="checkbox" value="{{ $doorType }}"
                                       class="shrink-0 mt-0.5 border-gray-200 rounded text-green-600 focus:ring-green-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-green-500 door-type-checkbox"
                                       {{ in_array($doorType, old('door_types', $defaultTask->door_types ?? [])) ? 'checked' : '' }}>
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
        @error('door_types')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('door_types.*')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('applies_to_door_types')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror

        <div class="mt-2 space-y-3" id="specific-locations-section">
            <div class="flex items-center">
                <input id="select_all_locations" name="select_all_locations" type="checkbox"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500">
                <label for="select_all_locations" class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Selecteer/Deselecteer Alles
                </label>
            </div>
            <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50/50 dark:bg-gray-800 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="locations_checkbox_group">
                    @if(isset($locations) && $locations->count() > 0)
                        @foreach($locations as $location)
                            <div class="flex items-center">
                                <input id="location_{{ $location->id }}" name="locations[]" type="checkbox" value="{{ $location->id }}"
                                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 location-checkbox"
                                       {{ in_array($location->id, old('locations', $selectedLocations ?? [])) ? 'checked' : '' }}>
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
        @error('locations.*')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('applies_to_all_locations')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Actie einde dag sectie --}}
    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
        <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Actie einde dag (optioneel)</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Specificeer acties die uitgevoerd moeten worden aan het eind van de dag voor deze taak.
        </p>
        
        <div class="space-y-4">
            <x-form-input 
                name="end_day_action_title"
                label="Titel eindactie"
                :value="$defaultTask->end_day_action_title ?? ''"
                placeholder=""
            />
            
            <x-form-textarea 
                name="end_day_action_description"
                label="Omschrijving eindactie"
                :value="$defaultTask->end_day_action_description ?? ''"
                placeholder="Beschrijf de specifieke acties die aan het eind van de dag uitgevoerd moeten worden..."
                rows="3"
            />
        </div>
    </div>

    {{-- Benodigdheden sectie --}}
    @if(isset($benodigdheden) && $benodigdheden->count() > 0)
    <div>
        <x-input-label class="block text-sm font-medium mb-2">Benodigdheden (optioneel)</x-input-label>
        <div class="mt-2 space-y-3">
            <div class="flex items-center">
                <input id="select_all_benodigdheden_default" name="select_all_benodigdheden" type="checkbox"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500">
                <label for="select_all_benodigdheden_default" class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Selecteer/Deselecteer Alles
                </label>
            </div>
            <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50/50 dark:bg-gray-800 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="benodigdheden_checkbox_group_default">
                    @foreach($benodigdheden as $benodigdheid)
                        <div class="flex items-center">
                            <input id="benodigdheid_default_{{ $benodigdheid->id }}" name="benodigdheden[]" type="checkbox" value="{{ $benodigdheid->id }}"
                                   class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 benodigdheid-checkbox-default"
                                   {{ in_array($benodigdheid->id, old('benodigdheden', $selectedBenodigdheden ?? [])) ? 'checked' : '' }}>
                            <label for="benodigdheid_default_{{ $benodigdheid->id }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $benodigdheid->naam }}
                                @if($benodigdheid->beschrijving)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($benodigdheid->beschrijving, 60) }}</span>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @error('benodigdheden')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('benodigdheden.*')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        // Set initial state
        toggleSections();

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                locationCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    });
</script>
@endpush

<div class="mt-8 flex items-center justify-end gap-x-2">
    <a href="{{ route('default-tasks.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
        Annuleren
    </a>
    <x-primary-button type="submit">
        {{ $submitButtonText ?? 'Opslaan' }}
    </x-primary-button>
</div> 