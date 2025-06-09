<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Standaardtaak Details: ') }} {{ $defaultTask->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="mb-4 flex justify-between items-center">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Standaardtaak: <span class="font-normal">{{ $defaultTask->title }}</span></h1>
                        <div>
                            <a href="{{ route('default-tasks.edit', $defaultTask) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:border-yellow-700 focus:ring ring-yellow-300 disabled:opacity-25 transition ease-in-out duration-150 mr-2">
                                Bewerken
                            </a>
                            <a href="{{ route('default-tasks.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Terug naar overzicht
                            </a>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Details</h3>
                        <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Titel</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $defaultTask->title }}</dd>
                            </div>
                            <div class="py-3 flex flex-col text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">Omschrijving</dt>
                                <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $defaultTask->description }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Aangemaakt op</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $defaultTask->created_at->format('d-m-Y H:i:s') }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Laatst bijgewerkt</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $defaultTask->updated_at->format('d-m-Y H:i:s') }}</dd>
                            </div>
                            <div class="py-3 flex justify-between text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">Aangemaakt door</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $defaultTask->creator?->name ?? 'Onbekend' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-2">Gebruikt door locaties:</h4>
                            @if($defaultTask->locations && $defaultTask->locations->count() > 0)
                                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400">
                                    @foreach($defaultTask->locations as $location)
                                        <li><a href="{{ route('locations.show', $location) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $location->name }}</a></li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Deze standaardtaak is nog niet aan locaties gekoppeld.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 