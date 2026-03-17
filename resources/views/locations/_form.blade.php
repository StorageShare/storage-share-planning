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
            <x-input-label for="bv" :value="__('BV')" />
            <x-text-input type="text" id="bv" name="bv" class="block mt-1 w-full" :value="old('bv', $location->bv ?? '')" />
            <x-input-error :messages="$errors->get('bv')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="lift" :value="__('Lift')" />
            <select id="lift" name="lift" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                <option value="1" {{ old('lift', $location->lift ?? '') == '1' ? 'selected' : '' }}>{{ __('Ja') }}</option>
                <option value="0" {{ old('lift', $location->lift ?? '') == '0' ? 'selected' : '' }}>{{ __('Nee') }}</option>
            </select>
            <x-input-error :messages="$errors->get('lift')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="type_deur" :value="__('Type Deur')" />
            <x-text-input type="text" id="type_deur" name="type_deur" class="block mt-1 w-full" :value="old('type_deur', $location->type_deur ?? '')" />
            <x-input-error :messages="$errors->get('type_deur')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="latitude" :value="__('Latitude')" />
            <x-text-input type="text" id="latitude" name="latitude" class="block mt-1 w-full" :value="old('latitude', $location->latitude ?? '')" />
            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="longitude" :value="__('Longitude')" />
            <x-text-input type="text" id="longitude" name="longitude" class="block mt-1 w-full" :value="old('longitude', $location->longitude ?? '')" />
            <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="total_m2_net" :value="__('Totaal m2 Netto')" />
            <x-text-input type="number" step="0.01" id="total_m2_net" name="total_m2_net" class="block mt-1 w-full" :value="old('total_m2_net', $location->total_m2_net ?? '')" />
            <x-input-error :messages="$errors->get('total_m2_net')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="total_m2_gross" :value="__('Totaal m2 Bruto')" />
            <x-text-input type="number" step="0.01" id="total_m2_gross" name="total_m2_gross" class="block mt-1 w-full" :value="old('total_m2_gross', $location->total_m2_gross ?? '')" />
            <x-input-error :messages="$errors->get('total_m2_gross')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="total_rooms" :value="__('Totaal Kamers')" />
            <x-text-input type="number" id="total_rooms" name="total_rooms" class="block mt-1 w-full" :value="old('total_rooms', $location->total_rooms ?? '')" />
            <x-input-error :messages="$errors->get('total_rooms')" class="mt-2" />
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
