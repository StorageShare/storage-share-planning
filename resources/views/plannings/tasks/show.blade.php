<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Geplande Taak Details: {{ $planning_task->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    @php
                        $hasAnyPhotos = ($planning_task->planningTaskPhotos->count() > 0)
                            || ($planning_task->completions && $planning_task->completions->pluck('photos')->flatten()->count() > 0);
                    @endphp
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
                        <div class="flex items-center gap-2">
                            <a href="{{ route('plannings.show', $planning_task->planning) }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Terug naar planning
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6"
                         x-data="{ selectedRoom: '{{ $planning_task->task->room ?? '' }}' }"
                         @room-linked.window="if($event.detail.taskId == {{ $planning_task->task_id ?? 'null' }}) selectedRoom = $event.detail.room">
                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Details</h3>
                            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="py-3 flex flex-col text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400 mb-1">Omschrijving</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $planning_task->description }}</dd>
                                </div>
                                @if($planning_task->feedback_information)
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Terugkoppeling informatie</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $planning_task->feedback_information }}</dd>
                                </div>
                                @endif
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
                                            {{ $planning_task->status->label() }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Foto's bij voltooien</h3>
                            @if($planning_task->planningTaskPhotos->count() > 0)
                                @php
                                    $planningPhotos = $planning_task->planningTaskPhotos->map(fn($photo) => $photo->url)->values()->all();
                                    $planningPhotoIds = $planning_task->planningTaskPhotos->pluck('id')->values()->all();
                                    $planningPhotoRooms = $planning_task->planningTaskPhotos->pluck('room')->values()->all();
                                @endphp
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" x-data="{
                                    planningPhotos: @json($planningPhotos),
                                    photoIds: @json($planningPhotoIds),
                                    photoRooms: @json($planningPhotoRooms)
                                }" @room-linked.window="
                                    const idx = $data.photoIds.indexOf($event.detail.photoId);
                                    if(idx !== -1) $data.photoRooms[idx] = $event.detail.room;
                                ">
                                    @foreach($planning_task->planningTaskPhotos as $index => $photo)
                                    <button type="button" class="focus:outline-none"
                                            @click="$dispatch('open-image-modal', {
                                                imageUrls: $data.planningPhotos,
                                                photoIds: $data.photoIds,
                                                photoType: 'planning',
                                                startIndex: {{ $index }},
                                                taskId: {{ $planning_task->task_id ?? 'null' }},
                                                locationId: {{ $planning_task->specificLocation->id ?? 'null' }},
                                                currentRooms: $data.photoRooms
                                            })">
                                        <img src="{{ $photo->url }}" alt="Taakfoto {{ $photo->id }}" class="rounded-md object-cover h-32 w-32 cursor-pointer hover:opacity-75 transition">
                                    </button>
                                    @endforeach
                                </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen foto's voor deze taak.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Foto Workflow Sectie --}}
                    @if($planning_task->task_id)
                        <div class="mt-6 p-6 border-t border-gray-200 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/10 rounded-lg shadow-sm"
                             x-data="{
                                rooms: [],
                                loadingRooms: false,
                                roomsError: false,
                                async init() {
                                    this.loadingRooms = true;
                                    this.roomsError = false;
                                    try {
                                        const response = await fetch('{{ route('photo-workflow.rooms', ['task' => $planning_task->task_id]) }}');
                                        const data = await response.json();
                                        if (data.success) {
                                            this.rooms = data.rooms;
                                        } else {
                                            this.roomsError = true;
                                        }
                                    } catch (e) {
                                        console.error('Failed to fetch rooms', e);
                                        this.roomsError = true;
                                    } finally {
                                        this.loadingRooms = false;
                                    }
                                }
                             }"
                             @room-linked.window="onRoomLinked($event.detail)">
                            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-300">Niet verhuurde ruimte vol workflow</h3>
                            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">Gebruik dit formulier om de foto van de ruimte rond te sturen naar alle klanten en het automatische opvolgingsproces te starten.</p>

                            <form action="{{ route('photo-workflow.distribute', ['task' => $planning_task->task_id]) }}" method="POST" class="mt-4">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                                    <div>
                                        <label for="room" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ruimte nummer/naam</label>

                                        <template x-if="rooms.length > 0">
                                            <select name="room" id="room" required
                                                    x-model="selectedRoom"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200">
                                                <option value="">Selecteer ruimte...</option>
                                                <template x-for="room in rooms" :key="room">
                                                    <option :value="room" x-text="room"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="loadingRooms">
                                            <div class="relative">
                                                <input type="text" disabled
                                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400"
                                                       placeholder="Ruimtes laden...">
                                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                                    <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="!loadingRooms && roomsError">
                                            <div class="mt-1 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>Fout bij het laden van ruimtes. Neem contact op met support.</span>
                                            </div>
                                        </template>

                                        <template x-if="!loadingRooms && !roomsError && rooms.length === 0">
                                            <div class="mt-1 text-sm text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                <span>Geen beschikbare ruimtes gevonden voor deze locatie.</span>
                                            </div>
                                        </template>
                                    </div>
                                    <div>
                                        <button type="submit"
                                                :disabled="loadingRooms || roomsError || rooms.length === 0"
                                                :class="(!loadingRooms && !roomsError && rooms.length > 0) ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Foto rondsturen & Proces starten
                                        </button>
                                    </div>
                                </div>
                                @if($planning_task->task->photo_process_step)
                                    <div class="mt-3 text-xs text-blue-600 dark:text-blue-400">
                                        Huidige status: <strong>{{ $planning_task->task->photo_process_step }}</strong>
                                        (Laatste update: {{ $planning_task->task->photo_process_at ? $planning_task->task->photo_process_at->format('d-m-Y H:i') : 'Onbekend' }})
                                    </div>
                                @endif
                            </form>
                        </div>
                    @endif

                    @if($planning_task->completions->isNotEmpty())
                    <div class="mt-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Voltooiingsgeschiedenis</h2>
                            @if($hasAnyPhotos)
                                <a href="{{ route('plannings.tasks.photos.download', $planning_task) }}"
                                   class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:border-blue-800 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"/><path d="M5 15a1 1 0 011 1v2a1 1 0 001 1h10a1 1 0 001-1v-2a1 1 0 112 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a1 1 0 011-1z"/></svg>
                                    Download alle foto’s
                                </a>
                            @endif
                        </div>
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
                                            $completionPhotoIds = $completion->photos->pluck('id')->values()->all();
                                            $completionPhotoRooms = $completion->photos->pluck('room')->values()->all();
                                            @endphp
                                            <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2 mt-2"
                                                 x-data='{
                                                    completionPhotos: @json($completionPhotos),
                                                    photoIds: @json($completionPhotoIds),
                                                    photoRooms: @json($completionPhotoRooms)
                                                 }'
                                                 @room-linked.window="
                                                    if($event.detail.photoType === 'completion') {
                                                        const idx = photoIds.indexOf($event.detail.photoId);
                                                        if(idx !== -1) photoRooms[idx] = $event.detail.room;
                                                    }
                                                 ">
                                                @foreach($completion->photos as $index => $photo)
                                                <button type="button" class="focus:outline-none"
                                                        @click="$dispatch('open-image-modal', {
                                                            imageUrls: $data.completionPhotos,
                                                            photoIds: $data.photoIds,
                                                            photoType: 'planning_completion',
                                                            startIndex: {{ $index }},
                                                            taskId: {{ $planning_task->task_id ?? 'null' }},
                                                            locationId: {{ $planning_task->specificLocation->id ?? 'null' }},
                                                            currentRooms: $data.photoRooms
                                                        })">
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
                                                $completionPhotoIds = $completion->photos->pluck('id')->values()->all();
                                                $completionPhotoRooms = $completion->photos->pluck('room')->values()->all();
                                                @endphp
                                                <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2 mt-2"
                                                     x-data='{
                                                        completionPhotos: @json($completionPhotos),
                                                        photoIds: @json($completionPhotoIds),
                                                        photoRooms: @json($completionPhotoRooms)
                                                     }'
                                                     @room-linked.window="
                                                        if($event.detail.photoType === 'completion') {
                                                            const idx = $data.photoIds.indexOf($event.detail.photoId);
                                                            if(idx !== -1) $data.photoRooms[idx] = $event.detail.room;
                                                        }
                                                     ">
                                                    @foreach($completion->photos as $index => $photo)
                                                    <button type="button" class="focus:outline-none"
                                                            @click="$dispatch('open-image-modal', {
                                                                imageUrls: $data.completionPhotos,
                                                                photoIds: $data.photoIds,
                                                                photoType: 'planning_completion',
                                                                startIndex: {{ $index }},
                                                                taskId: {{ $planning_task->task_id ?? 'null' }},
                                                                locationId: {{ $planning_task->specificLocation->id ?? 'null' }},
                                                                currentRooms: $data.photoRooms
                                                            })">
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
</x-app-layout>
