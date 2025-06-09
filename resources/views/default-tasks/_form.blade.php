<div class="space-y-6">
    <div>
        <x-input-label for="title" class="block text-sm font-medium mb-2">Titel</x-input-label>
        <x-text-input type="text" name="title" id="title" value="{{ old('title', $defaultTask->title ?? '') }}" required
                      class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('title') border-red-500 @enderror" />
        @error('title')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label for="description" class="block text-sm font-medium mb-2">Omschrijving</x-input-label>
        <textarea name="description" id="description" rows="4" required
                  class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('description') border-red-500 @enderror">{{ old('description', $defaultTask->description ?? '') }}</textarea>
        @error('description')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label for="estimated_time_minutes" class="block text-sm font-medium mb-2">Geschatte tijd (minuten)</x-input-label>
        <x-text-input type="number" name="estimated_time_minutes" id="estimated_time_minutes" value="{{ old('estimated_time_minutes', $defaultTask->estimated_time_minutes ?? 0) }}" min="0"
                      class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('estimated_time_minutes') border-red-500 @enderror" />
        @error('estimated_time_minutes')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label class="block text-sm font-medium mb-2">Locaties (optioneel)</x-input-label>
        <div class="mt-2 space-y-3">
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
    </div>
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

        // Optioneel: Update "Selecteer alles" status gebaseerd op individuele checkboxes
        // locationCheckboxes.forEach(checkbox => {
        //     checkbox.addEventListener('change', function () {
        //         if (!this.checked) {
        //             selectAllCheckbox.checked = false;
        //         }
        //         // Om "Selecteer alles" aan te vinken als alle items handmatig zijn geselecteerd:
        //         // let allChecked = true;
        //         // locationCheckboxes.forEach(cb => {
        //         //     if (!cb.checked) allChecked = false;
        //         // });
        //         // selectAllCheckbox.checked = allChecked;
        //     });
        // });
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