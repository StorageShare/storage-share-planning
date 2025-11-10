@php
    $isEdit = isset($vehicle);
    $selectedType = old('type', $isEdit ? ($vehicle->type?->value ?? '') : '');
@endphp

<div class="space-y-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Naam</label>
        <input type="text" name="name" id="name" value="{{ old('name', $isEdit ? ($vehicle->name ?? '') : '') }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="license_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kenteken</label>
        <input type="text" name="license_number" id="license_number" value="{{ old('license_number', $isEdit ? ($vehicle->license_number ?? '') : '') }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200" />
        @error('license_number')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
        <select name="type" id="type" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
            <option value="">-- Kies type --</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" {{ $selectedType === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
