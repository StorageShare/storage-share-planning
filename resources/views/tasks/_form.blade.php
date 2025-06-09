{{-- $location variabele wordt verwacht in de parent view voor 'create' --}}
{{-- $task variabele (met $task->location) wordt verwacht voor 'edit' --}}

@php
    $currentLocation = $location ?? $task->location ?? null;
    $currentTask = $task ?? null; // Zorg dat $task beschikbaar is, ook bij create (als null)
@endphp

<input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">

<div class="space-y-6">
    <div>
        <x-input-label for="title" class="block text-sm font-medium mb-2">Titel</x-input-label>
        <x-text-input type="text" name="title" id="title" value="{{ old('title', $currentTask->title ?? '') }}" required
               class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('title') border-red-500 @enderror" />
        @error('title')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label for="description" class="block text-sm font-medium mb-2">Omschrijving</x-input-label>
        <textarea name="description" id="description" rows="4" required
                  class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('description') border-red-500 @enderror">{{ old('description', $currentTask->description ?? '') }}</textarea>
        @error('description')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

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
            <x-text-input type="date" name="deadline" id="deadline" value="{{ old('deadline', isset($currentTask) && $currentTask->deadline ? $currentTask->deadline->format('Y-m-d') : '') }}"
                   class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('deadline') border-red-500 @enderror" />
            @error('deadline')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <x-input-label for="estimated_time_minutes" class="block text-sm font-medium mb-2">Geschatte tijd (minuten, optioneel)</x-input-label>
            <x-text-input type="number" name="estimated_time_minutes" id="estimated_time_minutes" step="1" min="0" max="99999" value="{{ old('estimated_time_minutes', $currentTask->estimated_time_minutes ?? 0) }}"
                   class="py-3 px-4 block w-full rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('estimated_time_minutes') border-red-500 @enderror" />
            @error('estimated_time_minutes')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if (isset($currentTask) && $currentTask->exists) {{-- Alleen tonen bij bewerken van een bestaande taak --}}
    <div>
        <x-input-label for="status" class="block text-sm font-medium mb-2">Status</x-input-label>
        <select name="status" id="status" required
                class="py-3 px-4 pe-9 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('status') border-red-500 @enderror">
            <option value="open" {{ old('status', $currentTask->status ?? 'open') == 'open' ? 'selected' : '' }}>Open</option>
            <option value="in_progress" {{ old('status', $currentTask->status ?? '') == 'in_progress' ? 'selected' : '' }}>In uitvoering</option>
            <option value="completed" {{ old('status', $currentTask->status ?? '') == 'completed' ? 'selected' : '' }}>Voltooid</option>
        </select>
        @error('status')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    @endif
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