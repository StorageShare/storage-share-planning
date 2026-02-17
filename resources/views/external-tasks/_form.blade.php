@props([
    'task' => null,
    'locations' => [],
    'priorities' => [],
    'statuses' => [],
    'isEdit' => false
])

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-form-input
            name="title"
            label="Titel"
            :value="$task?->title ?? ''"
            placeholder="Vul de titel van de taak in"
            required
        />

        <div>
            <x-input-label for="location_id" class="block text-sm font-medium mb-1 dark:text-gray-300">Locatie <span class="text-red-500">*</span></x-input-label>
            <select name="location_id" id="location_id" required
                    class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('location_id') border-red-500 @enderror">
                <option value="">Selecteer een locatie</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" {{ old('location_id', $task?->location_id) == $location->id ? 'selected' : '' }}>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
            @error('location_id')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <x-form-textarea
        name="description"
        label="Omschrijving"
        :value="$task?->description ?? ''"
        placeholder="Beschrijf de taak in detail"
        rows="4"
    />

    <x-form-input
        name="feedback_information"
        label="Wat moet er gebeuren na het uitvoeren van deze taak"
        :value="$task?->feedback_information ?? ''"
        placeholder="Beschrijf de actie die moet volgen op de uitvoering"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-form-input
            name="feedback_owner_name"
            label="Naam ontvanger terugkoppeling"
            :value="$task?->feedback_owner_name ?? ''"
            placeholder="Bijv: Jan Janssen"
        />

        <x-form-input
            name="feedback_emails"
            label="E-mailadres(sen) terugkoppeling (komma of puntkomma gescheiden)"
            :value="$task?->feedback_emails ?? ''"
            placeholder="Bijv: jan@voorbeeld.nl, kees@voorbeeld.nl"
        />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <x-input-label for="priority" class="block text-sm font-medium mb-1 dark:text-gray-300">Prioriteit <span class="text-red-500">*</span></x-input-label>
            <select name="priority" id="priority" required
                    class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('priority') border-red-500 @enderror">
                @foreach($priorities as $priorityCase)
                    <option value="{{ $priorityCase->value }}" {{ old('priority', $task?->priority?->value ?? \App\Enums\TaskPriority::NORMAL->value) == $priorityCase->value ? 'selected' : '' }}>
                        {{ $priorityCase->label() }}
                    </option>
                @endforeach
            </select>
            @error('priority')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        @if($isEdit)
        <div>
            <x-input-label for="status" class="block text-sm font-medium mb-1 dark:text-gray-300">Status <span class="text-red-500">*</span></x-input-label>
            <select name="status" id="status" required
                    class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('status') border-red-500 @enderror">
                @foreach($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}" {{ old('status', $task?->status?->value) == $statusCase->value ? 'selected' : '' }}>
                        {{ $statusCase->label() }}
                    </option>
                @endforeach
            </select>
            @error('status')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        @endif

        <div>
            <x-input-label for="external_deadline_at" class="block text-sm font-medium mb-1 dark:text-gray-300">Deadline (optioneel)</x-input-label>
            <x-form-input
                type="datetime-local"
                name="external_deadline_at"
                id="external_deadline_at"
                :value="$task?->external_deadline_at ? $task->external_deadline_at->format('Y-m-d\TH:i') : ''"
                class="py-3 px-4 block w-full text-sm"
            />
        </div>

        <x-form-input
            name="estimated_time_minutes"
            type="number"
            label="Geschatte tijd (minuten)"
            :value="$task?->estimated_time_minutes ?? 0"
            placeholder="0"
            min="0"
            max="99999"
        />
    </div>

    <div class="flex justify-end gap-x-3">
        <a href="{{ $isEdit ? route('external-backlog.show', $task) : route('external-backlog.index') }}"
           class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-900 dark:border-gray-700 dark:text-white dark:hover:bg-gray-800">
            Annuleren
        </a>
        <button type="submit"
                class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            {{ $isEdit ? 'Bijwerken' : 'Aanmaken' }}
        </button>
    </div>
</div>
