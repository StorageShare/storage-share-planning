<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Requirement Details: ') }} {{ $requirement->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="mb-4 flex justify-between items-center">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Benodigdheid: <span class="font-normal">{{ $requirement->name }}</span></h1>
                        <div>
                            <a href="{{ route('requirements.edit', $requirement) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 focus:outline-none focus:bg-yellow-600 active:bg-yellow-900 focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Bewerken') }}
                            </a>
                            <a href="{{ route('requirements.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 focus:outline-none focus:bg-gray-600 active:bg-gray-900 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Terug naar overzicht') }}
                            </a>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Details</h3>
                        <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Naam</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $requirement->name }}</dd>
                            </div>
                            <div class="py-3 flex flex-col text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">Beschrijving</dt>
                                <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $requirement->description ?: 'Geen beschrijving' }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Aangemaakt op</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $requirement->created_at->format('d-m-Y H:i:s') }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Laatst bijgewerkt</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $requirement->updated_at->format('d-m-Y H:i:s') }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Aangemaakt door</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $requirement->creator?->name ?? 'Onbekend' }}</dd>
                            </div>
                        </dl>

                        {{-- Automatisch vereist voor locaties sectie --}}
                        @if($requirement->requiredForLocations && $requirement->requiredForLocations->count() > 0)
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                                            🌍 Automatisch nodig voor locaties
                                        </h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                                                Deze benodigdheid wordt automatisch aan de paklijst toegevoegd wanneer een van de volgende locaties wordt gekozen in een planning:
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($requirement->requiredForLocations as $location)
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                        📍 {{ $location->name }}
                                                        <span class="ml-1 text-xs text-green-600 dark:text-green-300">({{ $location->city }})</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Backlog Taken --}}
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-2">Backlog taken:</h4>
                                    @if($requirement->tasks && $requirement->tasks->count() > 0)
                                        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                            @foreach($requirement->tasks as $task)
                                                <li>
                                                    <a href="{{ route('tasks.show', $task) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $task->title }}</a>
                                                    <span class="text-xs text-gray-500">({{ $task->location->name }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Deze benodigdheid is nog niet aan backlog taken gekoppeld.</p>
                                    @endif
                                </div>

                                {{-- Standaard Taken --}}
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-2">Standaard taken:</h4>
                                    @if($requirement->defaultTasks && $requirement->defaultTasks->count() > 0)
                                        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                            @foreach($requirement->defaultTasks as $defaultTask)
                                                <li>
                                                    <a href="{{ route('default-tasks.show', $defaultTask) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $defaultTask->title }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Deze benodigdheid is nog niet aan standaard taken gekoppeld.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
