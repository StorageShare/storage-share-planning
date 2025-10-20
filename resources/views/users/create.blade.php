<x-app-layout>
    <x-slot name="header">
    </x-slot>

    <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <div class="sm:flex sm:items-center sm:justify-between mb-6">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-lg font-medium text-gray-800 dark:text-white">Nieuwe Gebruiker</h2>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Maak een nieuwe gebruiker aan in het systeem.</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form action="{{ route('users.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input type="text" id="name" name="name" class="block mt-1 w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input type="email" id="email" name="email" class="block mt-1 w-full" :value="old('email')" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <select name="role" id="role" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role') == $role->value)>
                                    {{ match($role->value) {
                                        'admin' => 'Administrator',
                                        'algemeen_medewerker' => 'Algemeen Medewerker',
                                        'gebruiker' => 'Gebruiker',
                                        'customer_service' => 'Klantenservice',
                                        default => ucfirst($role->value),
                                    } }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div class="mt-8 flex items-center justify-end gap-x-2">
                        <a href="{{ route('users.index') }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
                            Annuleren
                        </a>
                        <x-primary-button type="submit">Gebruiker Aanmaken</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
