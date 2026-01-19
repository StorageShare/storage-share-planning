<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Checklist Item Afwijzen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ $item->title }}
                        </h3>
                        @if($item->description)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                {{ $item->description }}
                            </p>
                        @endif

                        @if($item->location)
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <strong>Locatie:</strong> {{ $item->location->name }}
                            </p>
                        @endif

                        @if($item->uploader)
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <strong>Geüpload door:</strong> {{ $item->uploader->name }}
                                @if($item->uploaded_at)
                                    op {{ $item->uploaded_at->format('d-m-Y H:i') }}
                                @endif
                            </p>
                        @endif

                        @if($item->photo_path)
                            <div class="mt-4">
                                <img src="{{ asset('storage/' . $item->photo_path) }}"
                                     alt="Checklist item foto"
                                     class="max-w-md h-48 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:opacity-75 transition"
                                     @click="$dispatch('open-image-modal', { imageUrls: ['{{ asset('storage/' . $item->photo_path) }}'], startIndex: 0 })">
                                <p class="mt-1 text-xs text-gray-500">{{ __('Klik op de foto om deze te vergroten') }}</p>
                            </div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.end-checklist.reject.process', $item) }}" class="space-y-6">
                        @csrf

                        <div>
                            <label for="admin_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reden voor afwijzing <span class="text-red-500">*</span>
                            </label>
                            <textarea id="admin_notes" name="admin_notes" rows="4" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Leg uit waarom dit item wordt afgewezen...">{{ old('admin_notes') }}</textarea>
                            @error('admin_notes')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Wilt u een nieuwe taak aanmaken?
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <p>Indien het checklist item niet correct is uitgevoerd, kunt u ervoor kiezen om een nieuwe taak aan te maken voor dit probleem.</p>
                                    </div>
                                    <div class="mt-4">
                                        <div class="flex items-center">
                                            <input id="create_new_task" name="create_new_task" type="checkbox" value="1"
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded"
                                                {{ old('create_new_task') ? 'checked' : '' }}>
                                            <label for="create_new_task" class="ml-2 block text-sm text-yellow-700 dark:text-yellow-300">
                                                Ja, maak een nieuwe taak aan voor dit probleem
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4">
                            <a href="{{ route('admin.tasks.review') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Annuleren
                            </a>

                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Item Afwijzen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
