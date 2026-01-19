<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <x-input-label for="name" :value="__('Naam')" />
            <x-text-input type="text" id="name" name="name" class="block mt-1 w-full" :value="old('name', $location->name ?? '')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="sync_external_id" :value="__('Externe Locatie (om te linken)')" />
            <select id="sync_external_id" name="sync_external_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                <option value="">{{ __('Geen (handmatige locatie)') }}</option>
                @foreach($externalLocations as $extLocation)
                    <option value="{{ $extLocation['id'] }}" {{ old('sync_external_id', $location->sync_external_id ?? '') == $extLocation['id'] ? 'selected' : '' }}>
                        {{ $extLocation['name'] }} (ID: {{ $extLocation['id'] }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('sync_external_id')" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Selecteer een externe locatie om deze te linken aan de synchronisatie.
            </p>
        </div>

        <div>
            <x-input-label for="address" :value="__('Adres')" />
            <x-text-input type="text" id="address" name="address" class="block mt-1 w-full" :value="old('address', $location->address ?? '')" required />
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="postal_code" :value="__('Postcode')" />
            <x-text-input type="text" id="postal_code" name="postal_code" class="block mt-1 w-full" :value="old('postal_code', $location->postal_code ?? '')" required />
            <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="city" :value="__('Stad')" />
            <x-text-input type="text" id="city" name="city" class="block mt-1 w-full" :value="old('city', $location->city ?? '')" required />
            <x-input-error :messages="$errors->get('city')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="outdoor_safe_code" :value="__('Outdoor Safe Code')" />
            <x-text-input type="text" id="outdoor_safe_code" name="outdoor_safe_code" class="block mt-1 w-full" :value="old('outdoor_safe_code', $location->outdoor_safe_code ?? '')" />
            <x-input-error :messages="$errors->get('outdoor_safe_code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="indoor_safe_code" :value="__('Indoor Safe Code')" />
            <x-text-input type="text" id="indoor_safe_code" name="indoor_safe_code" class="block mt-1 w-full" :value="old('indoor_safe_code', $location->indoor_safe_code ?? '')" />
            <x-input-error :messages="$errors->get('indoor_safe_code')" class="mt-2" />
        </div>

        <div class="md:col-span-2">
            <x-input-label for="outdoor_safe_content" :value="__('Outdoor Safe Content')" />
            <textarea id="outdoor_safe_content" name="outdoor_safe_content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('outdoor_safe_content', $location->outdoor_safe_content ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('outdoor_safe_content')" class="mt-2" />
        </div>

        <div class="md:col-span-2">
            <x-input-label for="indoor_safe_content" :value="__('Indoor Safe Content')" />
            <textarea id="indoor_safe_content" name="indoor_safe_content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('indoor_safe_content', $location->indoor_safe_content ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('indoor_safe_content')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="intratone_number" :value="__('Intratone Nummer')" />
            <x-text-input type="text" id="intratone_number" name="intratone_number" class="block mt-1 w-full" :value="old('intratone_number', $location->intratone_number ?? '')" />
            <x-input-error :messages="$errors->get('intratone_number')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="intratone_multiple_numbers" :value="__('Intratone Meerdere Nummers')" />
            <x-text-input type="text" id="intratone_multiple_numbers" name="intratone_multiple_numbers" class="block mt-1 w-full" :value="old('intratone_multiple_numbers', $location->intratone_multiple_numbers ?? '')" />
            <x-input-error :messages="$errors->get('intratone_multiple_numbers')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="gate_number" :value="__('Poort Nummer')" />
            <x-text-input type="text" id="gate_number" name="gate_number" class="block mt-1 w-full" :value="old('gate_number', $location->gate_number ?? '')" />
            <x-input-error :messages="$errors->get('gate_number')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="lift" :value="__('Lift')" />
            <x-text-input type="text" id="lift" name="lift" class="block mt-1 w-full" :value="old('lift', $location->lift ?? '')" />
            <x-input-error :messages="$errors->get('lift')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="bv" :value="__('BV')" />
            <x-text-input type="text" id="bv" name="bv" class="block mt-1 w-full" :value="old('bv', $location->bv ?? '')" />
            <x-input-error :messages="$errors->get('bv')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="type_deur" :value="__('Type Deur')" />
            <x-text-input type="text" id="type_deur" name="type_deur" class="block mt-1 w-full" :value="old('type_deur', $location->type_deur ?? '')" />
            <x-input-error :messages="$errors->get('type_deur')" class="mt-2" />
        </div>
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
