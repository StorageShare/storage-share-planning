<div class="space-y-6">
    <div>
        <x-input-label for="name" class="block text-sm font-medium mb-2">Naam</x-input-label>
        <x-text-input type="text" name="name" id="name" value="{{ old('name', $location->name ?? '') }}" required
               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('name') border-red-500 @enderror" />
        @error('name')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label for="address" class="block text-sm font-medium mb-2">Adres (optioneel)</x-input-label>
        <x-text-input type="text" name="address" id="address" value="{{ old('address', $location->address ?? '') }}"
               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none @error('address') border-red-500 @enderror" />
        @error('address')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-input-label for="description" class="block text-sm font-medium mb-2">Omschrijving (optioneel)</x-input-label>
        <textarea name="description" id="description" rows="4"
                  class="py-3 px-4 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm @error('description') border-red-500 @enderror">{{ old('description', $location->description ?? '') }}</textarea>
        @error('description')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mt-8 flex items-center justify-end gap-x-2">
        <a href="{{ route('locations.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
            Annuleren
        </a>
        <x-primary-button type="submit">
            {{ $submitButtonText ?? 'Opslaan' }}
        </x-primary-button>
    </div>
</div> 