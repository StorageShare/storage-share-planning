<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Taak Details: {{ $task->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Taak: <span class="font-normal">{{ $task->title }}</span></h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Locatie: <a href="{{ route('locations.show', $task->location) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $task->location->name }}</a>
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('tasks.edit', $task) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:border-yellow-700 focus:ring ring-yellow-300 disabled:opacity-25 transition ease-in-out duration-150 mr-2">
                                Bewerken
                            </a>
                            <a href="{{ route('locations.tasks.index', $task->location) }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Terug naar taken van {{ $task->location->name }}
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Details Taak</h3>
                            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Titel</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->title }}</dd>
                                </div>
                                <div class="py-3 flex flex-col text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400 mb-1">Omschrijving</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $task->description }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Prioriteit</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @switch($task->priority->value)
                                                @case(App\Enums\TaskPriority::HIGH->value) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                @case(App\Enums\TaskPriority::NORMAL->value) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                @case(App\Enums\TaskPriority::LOW->value) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @break
                                            @endswitch
                                        ">
                                            {{ $task->priority->label() }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @switch($task->status)
                                                @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                @case('in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endswitch
                                        ">
                                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Deadline</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->deadline ? $task->deadline->format('d-m-Y') : 'N.v.t.' }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Geschatte tijd (minuten)</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->estimated_time_minutes ?? 'N.v.t.' }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Aangemaakt op</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->created_at->format('d-m-Y H:i:s') }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Laatst bijgewerkt</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->updated_at->format('d-m-Y H:i:s') }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Aangemaakt door</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->creator?->name ?? 'Onbekend' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Foto's</h3>
                            @if($task->taskPhotos && $task->taskPhotos->count() > 0)
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    @foreach($task->taskPhotos as $photo)
                                        <div>
                                            <img src="{{ $photo->url }}" alt="Taakfoto {{ $photo->id }}" class="rounded-md object-cover h-32 w-full">
                                            {{-- Toevoegen: link om foto te verwijderen --}}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen foto's voor deze taak.</p>
                            @endif
                            {{-- Formulier om foto's te uploaden is in _form.blade.php, eventueel hier een directe link/knop --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 