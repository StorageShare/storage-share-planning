@if(isset($isCreate) || isset($isEdit))
<x-modal name="quick-task-modal" focusable>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Nieuwe taak aanmaken voor <span id="quick-task-location-name"></span>
        </h2>

        <form id="quick-task-form" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="location_id" id="quick-task-location-id">

            <div>
                <x-input-label for="quick-task-title" value="Titel" />
                <x-text-input id="quick-task-title" name="title" type="text" class="mt-1 block w-full" required />
            </div>

            <div>
                <x-input-label for="quick-task-description" value="Omschrijving" />
                <textarea id="quick-task-description" name="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="quick-task-priority" value="Prioriteit" />
                    <select id="quick-task-priority" name="priority" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                        @foreach(App\Enums\TaskPriority::cases() as $priorityCase)
                            <option value="{{ $priorityCase->value }}" {{ $priorityCase->value == App\Enums\TaskPriority::NORMAL->value ? 'selected' : '' }}>
                                {{ $priorityCase->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="quick-task-estimated-time" value="Geschatte tijd (minuten)" />
                    <x-text-input id="quick-task-estimated-time" name="estimated_time_minutes" type="number" min="0" class="mt-1 block w-full" value="0" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Annuleren
                </x-secondary-button>

                <x-primary-button class="ms-3" id="quick-task-submit-btn">
                    Taak Opslaan
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
@endif
