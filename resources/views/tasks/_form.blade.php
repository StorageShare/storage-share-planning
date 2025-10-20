{{-- $location variabele wordt verwacht in de parent view voor 'create' --}}
{{-- $task variabele (met $task->location) wordt verwacht voor 'edit' --}}

@php
    $currentLocation = $location ?? $task->location ?? null;
    $currentTask = $task ?? null; // Zorg dat $task beschikbaar is, ook bij create (als null)
    $prefillData = $prefill ?? []; // Prefilled data for new tasks
@endphp

<input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">

<div class="space-y-6">
    <x-form-input
        name="title"
        label="Titel"
        :value="$prefillData['title'] ?? $currentTask->title ?? ''"
        placeholder="Vul de titel van de taak in"
        required
    />

    <x-form-textarea
        name="description"
        label="Omschrijving"
        :value="$prefillData['description'] ?? $currentTask->description ?? ''"
        placeholder="Beschrijf de taak in detail"
        rows="4"
        required
    />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <x-input-label for="priority" class="block text-sm font-medium mb-2">Prioriteit</x-input-label>
            <select name="priority" id="priority"
                    class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('priority') border-red-500 @enderror">
                @foreach(App\Enums\TaskPriority::cases() as $priorityCase)
                    <option value="{{ $priorityCase->value }}" {{ old('priority', $currentTask->priority->value ?? App\Enums\TaskPriority::NORMAL->value) == $priorityCase->value ? 'selected' : '' }}>
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
                <x-text-input type="text" name="deadline" id="deadline" value="{{ old('deadline', isset($currentTask) && $currentTask->deadline ? $currentTask->deadline->format('Y-m-d') : '') }}"
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
            :value="$currentTask->estimated_time_minutes ?? 0"
            placeholder="0"
            min="0"
            max="99999"
        />
    </div>
    @anyrole('admin', 'facilities_coordinator')
    {{-- Terugkerende taak sectie --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
        <h3 class="text-md font-medium text-blue-900 dark:text-blue-100 mb-4">🔄 Terugkerende Taak (optioneel)</h3>
        <p class="text-sm text-blue-700 dark:text-blue-300 mb-4">
            Maak van deze taak een terugkerende taak die automatisch opnieuw wordt aangemaakt na goedkeuring.
        </p>

        <div class="space-y-4">
            <div class="flex items-center">
                <input id="is_recurring" name="is_recurring" type="checkbox" value="1"
                       class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
                       {{ old('is_recurring', $currentTask->is_recurring ?? false) ? 'checked' : '' }}>
                <label for="is_recurring" class="ms-3 text-sm font-medium text-blue-800 dark:text-blue-200">
                    Deze taak is terugkerend
                </label>
            </div>

            <div id="recurring-options" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="{{ old('is_recurring', $currentTask->is_recurring ?? false) ? '' : 'display: none;' }}">
                <div>
                    <x-input-label for="recurring_interval_value" class="block text-sm font-medium mb-2 text-blue-800 dark:text-blue-200">Herhaal elke</x-input-label>
                    <x-text-input type="number" name="recurring_interval_value" id="recurring_interval_value"
                           value="{{ old('recurring_interval_value', $currentTask->recurring_interval_value ?? 1) }}"
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
                        <option value="days" {{ old('recurring_interval_type', $currentTask->recurring_interval_type ?? '') == 'days' ? 'selected' : '' }}>Dagen</option>
                        <option value="weeks" {{ old('recurring_interval_type', $currentTask->recurring_interval_type ?? '') == 'weeks' ? 'selected' : '' }}>Weken</option>
                        <option value="months" {{ old('recurring_interval_type', $currentTask->recurring_interval_type ?? '') == 'months' ? 'selected' : '' }}>Maanden</option>
                        <option value="years" {{ old('recurring_interval_type', $currentTask->recurring_interval_type ?? '') == 'years' ? 'selected' : '' }}>Jaren</option>
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

    @if (isset($currentTask) && $currentTask->exists) {{-- Alleen tonen bij bewerken van een bestaande taak --}}
    <div>
        <x-input-label for="status" class="block text-sm font-medium mb-2">Status</x-input-label>
        <select name="status" id="status" required
                class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
            <option value="concept" {{ old('status', $currentTask->status ?? '') == 'concept' ? 'selected' : '' }}>Concept</option>
            <option value="open" {{ old('status', $currentTask->status ?? 'open') == 'open' ? 'selected' : '' }}>Open</option>
            <option value="in_progress" {{ old('status', $currentTask->status ?? '') == 'in_progress' ? 'selected' : '' }}>In uitvoering</option>
            <option value="completed" {{ old('status', $currentTask->status ?? '') == 'completed' ? 'selected' : '' }}>Voltooid</option>
        </select>
        @error('status')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    @endif

    {{-- Benodigdheden sectie --}}
    @if(isset($benodigdheden) && $benodigdheden->count() > 0)
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
            <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50/50 dark:bg-gray-800 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2" id="benodigdheden_checkbox_group">
                    @foreach($benodigdheden as $benodigdheid)
                        <div class="flex items-center">
                            <input id="benodigdheid_{{ $benodigdheid->id }}" name="benodigdheden[]" type="checkbox" value="{{ $benodigdheid->id }}"
                                   class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500 benodigdheid-checkbox"
                                   {{ in_array($benodigdheid->id, old('benodigdheden', $selectedBenodigdheden ?? [])) ? 'checked' : '' }}>
                            <label for="benodigdheid_{{ $benodigdheid->id }}" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
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
                :value="$currentTask->end_day_action_title ?? ''"
                placeholder=""
            />

            <div>
                <x-input-label for="end_day_action_description" class="block text-sm font-medium mb-2">Omschrijving eindactie</x-input-label>
                <textarea name="end_day_action_description" id="end_day_action_description" rows="3"
                          placeholder="Beschrijf de specifieke acties die aan het eind van de dag uitgevoerd moeten worden..."
                          class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('end_day_action_description') border-red-500 @enderror">{{ old('end_day_action_description', $currentTask->end_day_action_description ?? '') }}</textarea>
                @error('end_day_action_description')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
    @endanyrole

    {{-- Foto sectie --}}
    <div>
        <x-input-label class="block text-sm font-medium mb-2">Foto's</x-input-label>

        @if (isset($currentTask) && $currentTask->exists && $currentTask->taskPhotos && $currentTask->taskPhotos->count() > 0)
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Huidige foto's</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" id="existing-photos">
                    @foreach($currentTask->taskPhotos as $photo)
                        <div class="relative group" data-photo-id="{{ $photo->id }}">
                            <img src="{{ $photo->url }}" alt="Taakfoto" class="w-full h-32 object-cover rounded-lg shadow">
                            <button type="button"
                                    onclick="removeExistingPhoto({{ $photo->id }})"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="mt-4">
                    <label for="new-photos" class="cursor-pointer">
                        <span class="mt-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                            Foto's toevoegen
                        </span>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            PNG, JPG, GIF tot 20MB (meerdere bestanden mogelijk)
                        </span>
                    </label>
                    <input id="new-photos" type="file" name="photos[]" multiple accept="image/*" class="sr-only" onchange="previewNewPhotos(event)">
                </div>
            </div>
        </div>

        <div id="new-photos-preview" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 hidden"></div>

        @error('photos')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('photos.*')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-x-2">
    @if ($currentLocation)
        <a href="{{ route('locations.tasks.index', $currentLocation) }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
            Annuleren
        </a>
    @else {{-- Fallback als $currentLocation niet beschikbaar is, bv. bij een shallow route voor edit --}}
    <a href="{{ route('locations.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
        Terug naar Locaties
    </a>
    @endif
    <x-primary-button type="submit">
        {{ $submitButtonText ?? 'Opslaan' }}
    </x-primary-button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllBenodigdhedenCheckbox = document.getElementById('select_all_benodigdheden');
    const benodigdheidCheckboxes = document.querySelectorAll('.benodigdheid-checkbox');

    if (selectAllBenodigdhedenCheckbox) {
        selectAllBenodigdhedenCheckbox.addEventListener('change', function () {
            benodigdheidCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Recurring task functionality
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

            recurringPreview.textContent = `📅 Deze taak wordt elke ${value} ${typeLabels[type]} automatisch opnieuw aangemaakt na goedkeuring.`;
            recurringPreview.style.display = 'block';
        } else {
            recurringPreview.style.display = 'none';
        }
    }

    if (intervalValue) {
        intervalValue.addEventListener('input', updateRecurringPreview);
    }
    if (intervalType) {
        intervalType.addEventListener('change', updateRecurringPreview);
    }

    // Initial preview update if values are already set
    if (isRecurringCheckbox && isRecurringCheckbox.checked) {
        updateRecurringPreview();
    }
});

function previewNewPhotos(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('new-photos-preview');

    // Clear previous previews
    previewContainer.innerHTML = '';

    if (files.length > 0) {
        previewContainer.classList.remove('hidden');

        Array.from(files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'relative group';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview" class="w-full h-32 object-cover rounded-lg shadow">
                    <button type="button"
                            onclick="removeNewPhoto(${index})"
                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                previewContainer.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    } else {
        previewContainer.classList.add('hidden');
    }
}

function removeNewPhoto(index) {
    const input = document.getElementById('new-photos');
    const dt = new DataTransfer();
    const files = input.files;

    Array.from(files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });

    input.files = dt.files;
    previewNewPhotos({ target: input });
}

function removeExistingPhoto(photoId) {
    if (confirm('Weet je zeker dat je deze foto wilt verwijderen?')) {
        fetch(`/api/v1/task-photos/${photoId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (response.ok) {
                const photoElement = document.querySelector(`[data-photo-id="${photoId}"]`);
                if (photoElement) {
                    photoElement.remove();
                }
            } else {
                alert('Er is een fout opgetreden bij het verwijderen van de foto.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Er is een fout opgetreden bij het verwijderen van de foto.');
        });
    }
}
</script>
@endpush
