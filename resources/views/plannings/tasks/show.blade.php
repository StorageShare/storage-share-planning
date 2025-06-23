<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Geplande Taak Details: {{ $planning_task->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $planning_task->title }}</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Planning: <a href="{{ route('plannings.show', $planning_task->planning) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $planning_task->planning->planned_date->format('d-m-Y') }}</a>
                            </p>
                            @if($planning_task->specificLocation)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Locatie: <a href="{{ route('locations.show', $planning_task->specificLocation) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $planning_task->specificLocation->name }}</a>
                            </p>
                            @endif
                        </div>
                        <div>
                            <a href="{{ route('plannings.show', $planning_task->planning) }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Terug naar planning
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Details</h3>
                            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="py-3 flex flex-col text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400 mb-1">Omschrijving</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $planning_task->description }}</dd>
                                </div>
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @switch($planning_task->status)
                                                @case('review') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 @break
                                                @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                @default bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @endswitch
                                        ">
                                            {{ ucfirst($planning_task->status) }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Foto's bij voltooien</h3>
                            @if($planning_task->planningTaskPhotos->count() > 0)
                            @php
                            $planningPhotos = $planning_task->planningTaskPhotos->map(fn($photo) => Storage::url($photo->path))->values()->all();
                            @endphp
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" x-data='{ planningPhotos: @json($planningPhotos) }'>
                                @foreach($planning_task->planningTaskPhotos as $index => $photo)
                                <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: planningPhotos, startIndex: {{ $index }} })">
                                    <img src="{{ Storage::url($photo->path) }}" alt="Taakfoto {{ $photo->id }}" class="rounded-md object-cover h-32 w-32 cursor-pointer hover:opacity-75 transition">
                                </button>
                                @endforeach
                            </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen foto's voor deze taak.</p>
                            @endif
                        </div>
                    </div>

                    @if($planning_task->completions->isNotEmpty())
                    <div class="mt-8">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Voltooiingsgeschiedenis</h2>
                        <div class="space-y-6">
                            @foreach($planning_task->completions as $completion)
                                @if($completion->review_outcome === 'skipped')
                                    {{-- Special display for Skipped tasks --}}
                                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm border-l-4 border-gray-400">
                                        <div class="flex justify-between items-center mb-2">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                Taak overgeslagen door: {{ $completion->user->name }} op {{ $completion->created_at->format('d-m-Y \o\m H:i') }}
                                            </p>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                Overgeslagen
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Reden: {{ $completion->comment }}</p>

                                        @if($completion->photos->isNotEmpty())
                                        <div class="mt-4">
                                            <h5 class="text-md font-semibold text-gray-800 dark:text-gray-200">Bijgevoegde foto's</h5>
                                            @php
                                            $completionPhotos = $completion->photos->map(fn($photo) => Storage::url($photo->file_path))->values()->all();
                                            @endphp
                                            <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2 mt-2" x-data='{ completionPhotos: @json($completionPhotos) }'>
                                                @foreach($completion->photos as $index => $photo)
                                                <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: completionPhotos, startIndex: {{ $index }} })">
                                                    <img src="{{ Storage::url($photo->file_path) }}" alt="Voltooiingsfoto" class="rounded-md object-cover h-24 w-24 cursor-pointer hover:opacity-75 transition">
                                                </button>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                @else
                                    {{-- Regular completion entry --}}
                                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm">
                                        <div class="flex flex-col">
                                            <div class="flex-1">
                                                <div class="flex justify-between items-center mb-2">
                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                        Ingediend door: {{ $completion->user->name }} op {{ $completion->created_at->format('d-m-Y \o\m H:i') }}
                                                    </p>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $completion->is_fully_completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                        {{ $completion->is_fully_completed ? 'Volledig voltooid' : 'Niet volledig voltooid' }}
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Notities: {{ $completion->comment }}</p>
                                            </div>
                                            @if($completion->photos->isNotEmpty())
                                            <div class="mt-4">
                                                <h5 class="text-md font-semibold text-gray-800 dark:text-gray-200">Foto's</h5>
                                                @php
                                                $completionPhotos = $completion->photos->map(fn($photo) => Storage::url($photo->file_path))->values()->all();
                                                @endphp
                                                <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2 mt-2" x-data='{ completionPhotos: @json($completionPhotos) }'>
                                                    @foreach($completion->photos as $index => $photo)
                                                    <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: completionPhotos, startIndex: {{ $index }} })">
                                                        <img src="{{ Storage::url($photo->file_path) }}" alt="Voltooiingsfoto" class="rounded-md object-cover h-24 w-24 cursor-pointer hover:opacity-75 transition">
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Review block (excluding reopened) --}}
                                    @if($completion->review_outcome && $completion->review_outcome !== 'reopened')
                                    <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-sm mt-4 ml-6 border-l-4 @if($completion->review_outcome == 'approved') border-green-500 @else border-red-500 @endif">
                                        <div class="flex justify-between items-center mb-2">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                Beoordeling door {{ $completion->reviewer->name ?? 'Admin' }} op {{ optional($completion->reviewed_at)->format('d-m-Y \o\m H:i') }}
                                            </p>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    @if($completion->review_outcome == 'approved')
                                                        bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                    @else
                                                        bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                    @endif
                                                ">
                                                {{ ucfirst($completion->review_outcome) }}
                                            </span>
                                        </div>
                                        @if($completion->review_notes)
                                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $completion->review_notes }}</p>
                                        @else
                                        <p class="text-sm italic text-gray-500 dark:text-gray-400">Geen opmerkingen toegevoegd.</p>
                                        @endif
                                    </div>
                                    @endif
                                @endif
                                
                                {{-- Special block for Reopened --}}
                                @if($completion->review_outcome === 'reopened')
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-sm border-l-4 border-yellow-500">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            Heropend door {{ $completion->reviewer->name ?? 'Admin' }} op {{ optional($completion->reviewed_at)->format('d-m-Y \o\m H:i') }}
                                        </p>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Heropend
                                        </span>
                                    </div>
                                    @if($completion->review_notes)
                                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $completion->review_notes }}</p>
                                    @else
                                    <p class="text-sm italic text-gray-500 dark:text-gray-400">Geen opmerkingen toegevoegd.</p>
                                    @endif
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <x-modal-image />
</x-app-layout>