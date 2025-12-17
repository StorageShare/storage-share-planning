<div class="space-y-6">
    <x-form-input
        name="title"
        label="Titel"
        :value="$defaultVehicleTask->title ?? ''"
        placeholder="Vul de titel van de voertuigtaak in"
        required
    />

    <x-form-textarea
        name="description"
        label="Omschrijving"
        :value="$defaultVehicleTask->description ?? ''"
        placeholder="Beschrijf de standaard voertuigtaak"
        rows="4"
    />

    <x-form-input
        name="estimated_time_minutes"
        type="number"
        label="Geschatte tijd (minuten)"
        :value="isset($defaultVehicleTask) ? $defaultVehicleTask->estimated_time_minutes : old('estimated_time_minutes')"
        placeholder="0"
        min="0"
        max="99999"
    />

    <div class="flex items-center">
        <input id="active" name="active" type="checkbox" value="1"
               class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:checked:bg-blue-500"
               {{ old('active', $defaultVehicleTask->active ?? true) ? 'checked' : '' }}>
        <label for="active" class="ms-3 text-sm text-gray-700 dark:text-gray-300">
            Actief (zichtbaar voor selectie)
        </label>
    </div>

    <div class="flex items-center gap-x-3">
        <button type="submit"
                class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Opslaan
        </button>
        <a href="{{ route('default-vehicle-tasks.index') }}"
           class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
            Annuleren
        </a>
    </div>
</div>
