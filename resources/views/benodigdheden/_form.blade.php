<div class="space-y-6">
    <div>
        <x-input-label for="naam" class="block text-sm font-medium mb-2">Naam</x-input-label>
        <x-text-input type="text" name="naam" id="naam" value="{{ old('naam', $benodigdheid->naam ?? '') }}" required
                      class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none" />
    </div>

    <div>
        <x-input-label for="beschrijving" class="block text-sm font-medium mb-2">Beschrijving (optioneel)</x-input-label>
        <textarea name="beschrijving" id="beschrijving" rows="4"
                  class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('beschrijving', $benodigdheid->beschrijving ?? '') }}</textarea>
    </div>

    {{-- Automatisch nodig voor locaties sectie --}}
    @if(isset($locations) && $locations->count() > 0)
    <div>
        <x-input-label class="block text-sm font-medium mb-2">Automatisch nodig voor locaties (optioneel)</x-input-label>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Selecteer de locaties waar deze benodigdheid automatisch aan de paklijst wordt toegevoegd wanneer de locatie wordt gekozen in een planning.</p>
        
        <div class="mt-2 space-y-3">
            <div class="flex items-center">
                <input id="select_all_locations" name="select_all_locations" type="checkbox"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500">
                <label for="select_all_locations" class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Selecteer/Deselecteer Alles
                </label>
            </div>
            <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50/50 dark:bg-gray-800 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="locations_checkbox_group">
                    @foreach($locations as $location)
                        <div class="flex items-center">
                            <input id="location_{{ $location->id }}" name="required_for_locations[]" type="checkbox" value="{{ $location->id }}"
                                   class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 location-checkbox"
                                   {{ in_array($location->id, old('required_for_locations', $selectedLocations ?? [])) ? 'checked' : '' }}>
                            <label for="location_{{ $location->id }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $location->name }}
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $location->city }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="mt-8 flex items-center justify-end gap-x-2">
    <a href="{{ route('benodigdheden.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
        Annuleren
    </a>
    <x-primary-button type="submit">
        {{ $submitButtonText ?? 'Opslaan' }}
    </x-primary-button>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAllCheckbox = document.getElementById('select_all_locations');
        const locationCheckboxes = document.querySelectorAll('.location-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                locationCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // Update "Select All" checkbox when individual checkboxes change
        locationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                const allChecked = Array.from(locationCheckboxes).every(cb => cb.checked);
                const noneChecked = Array.from(locationCheckboxes).every(cb => !cb.checked);
                
                if (allChecked) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (noneChecked) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            });
        });
    });
</script>
@endpush 