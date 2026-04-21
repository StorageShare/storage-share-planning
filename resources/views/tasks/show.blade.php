<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Taak Details: {{ $task->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    @php
                        // If this task is viewed in the context of a planning (/tasks/:id?planning=:planningid),
                        // try to find the related PlanningTask so we can offer the ZIP download button
                        $planningTaskForTask = null;
                        $hasAnyPlanningPhotos = false;
                        if (request()->has('planning')) {
                            $planningTaskForTask = \App\Models\PlanningTask::with(['planningTaskPhotos', 'completions.photos'])
                                ->where('planning_id', request()->integer('planning'))
                                ->where('task_id', $task->id)
                                ->first();

                            if ($planningTaskForTask) {
                                $hasAnyPlanningPhotos = ($planningTaskForTask->planningTaskPhotos->count() > 0)
                                    || ($planningTaskForTask->completions && $planningTaskForTask->completions->pluck('photos')->flatten()->count() > 0);
                            }
                        }
                    @endphp
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Taak: <span class="font-normal">{{ $task->title }}</span></h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Locatie: <a href="{{ route('locations.show', $task->location) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-600">{{ $task->location->name }}</a>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(Auth::user()->isAdmin() && ($task->status === App\Enums\TaskStatus::REVIEW || $task->status === App\Enums\TaskStatus::IN_REVIEW))
                                <form action="{{ route('tasks.approve', $task) }}" method="POST" class="inline-block">
                                    @csrf
                                    <x-primary-button type="submit">
                                        {{ $task->status === App\Enums\TaskStatus::IN_REVIEW ? 'Accepteren' : 'Goedkeuren' }}
                                    </x-primary-button>
                                </form>
                                @if($task->status === App\Enums\TaskStatus::REVIEW)
                                <form action="{{ route('tasks.reject', $task) }}" method="POST" class="inline-block">
                                    @csrf
                                    <x-danger-button type="submit">Afkeuren</x-danger-button>
                                </form>
                                @endif
                                <form action="{{ route('tasks.convert-to-external', $task) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze taak wilt omzetten naar een externe taak? De huidige taak wordt verwijderd.')">
                                    @csrf
                                    <x-secondary-button type="submit">
                                        Omzetten naar extern
                                    </x-secondary-button>
                                </form>
                            @endif
                            @if(Auth::user()->isAdmin())
                            <a href="{{ route('tasks.edit', $task) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:border-yellow-700 focus:ring ring-yellow-300 disabled:opacity-25 transition ease-in-out duration-150 mr-2">
                                Bewerken
                            </a>
                            @endif
                            @if(request()->has('planning'))
                                <a href="{{ route('plannings.show', request()->get('planning')) }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    Terug naar planning
                                </a>
                            @else
                                <a href="{{ route('locations.tasks.index', $task->location) }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    Terug naar taken van {{ $task->location->name }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6"
                         x-data='{ currentRoom: "{{ $task->room ?? "" }}" }'
                         @room-linked.window="if($event.detail.taskId == {{ $task->id }}) $data.currentRoom = $event.detail.room">
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
                                @if($task->feedback_information)
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Terugkoppeling informatie</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $task->feedback_information }}</dd>
                                </div>
                                @endif
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
                                                @case(App\Enums\TaskStatus::CONCEPT) bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200 @break
                                                @case(App\Enums\TaskStatus::OPEN) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                @case(App\Enums\TaskStatus::IN_PROGRESS) bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                @case(App\Enums\TaskStatus::IN_REVIEW) bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 @break
                                                @case(App\Enums\TaskStatus::REVIEW) bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 @break
                                                @case(App\Enums\TaskStatus::COMPLETED) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                @case(App\Enums\TaskStatus::CLOSED) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @break
                                            @endswitch
                                        ">
                                            {{ $task->status->label() }}
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
                                @if($task->is_recurring)
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Terugkerende taak</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            🔄 {{ $task->getRecurringIntervalDescription() }}
                                        </span>
                                    </dd>
                                </div>

                                @endif
                                @if($task->parent_recurring_task_id)
                                <div class="py-3 flex justify-between text-sm font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Onderdeel van terugkerende taak</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('tasks.show', $task->parent_recurring_task_id) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-600">
                                            Bekijk hoofdtaak #{{ $task->parent_recurring_task_id }}
                                        </a>
                                    </dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <div class="bg-white dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Foto's</h3>
                            @if($task->taskPhotos && $task->taskPhotos->count() > 0)
                                @php
                                    $taskPhotos = $task->taskPhotos->map(fn($photo) => $photo->url)->values()->all();
                                    $taskPhotoIds = $task->taskPhotos->pluck('id')->values()->all();
                                @endphp
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" x-data='{
                                    taskPhotos: {{ json_encode($taskPhotos) }},
                                    photoIds: {{ json_encode($taskPhotoIds) }}
                                }'>
                                    @foreach($task->taskPhotos as $index => $photo)
                                        <button type="button" class="focus:outline-none group"
                                                @click="$dispatch('open-image-modal', {
                                                    imageUrls: $data.taskPhotos,
                                                    photoIds: $data.photoIds,
                                                    photoType: 'task_photo',
                                                    startIndex: {{ $index }},
                                                    taskId: {{ $task->id }},
                                                    locationId: {{ $task->location_id ?? 'null' }},
                                                    currentRoom: currentRoom
                                                })">
                                            <img src="{{ $photo->url }}" alt="Taakfoto {{ $photo->id }}" class="rounded-md object-cover h-32 w-full cursor-pointer hover:opacity-75 transition">
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen foto's voor deze taak.</p>
                            @endif
                        </div>
                    </div>

                    @if($completion_history->isNotEmpty())
                    <div class="mt-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Voltooiingsgeschiedenis</h2>
                            @if($planningTaskForTask && $hasAnyPlanningPhotos)
                                <a href="{{ route('plannings.tasks.photos.download', $planningTaskForTask) }}"
                                   class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:border-blue-800 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"/><path d="M5 15a1 1 0 011 1v2a1 1 0 001 1h10a1 1 0 001-1v-2a1 1 0 112 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a1 1 0 011-1z"/></svg>
                                    Download alle foto’s
                                </a>
                            @endif
                        </div>
                        <div class="space-y-6">
                            @foreach($completion_history as $completion)
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
                                            @endphp
                                                <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2 mt-2" x-data='{
                                                    completionPhotos: {{ json_encode($completionPhotos) }},
                                                    photoIds: {{ json_encode($completionPhotoIds) }}
                                                }'>
                                                    @foreach($completion->photos as $index => $photo)
                                                    <button type="button" class="focus:outline-none"
                                                            @click="$dispatch('open-image-modal', {
                                                                imageUrls: $data.completionPhotos,
                                                                photoIds: $data.photoIds,
                                                                photoType: 'completion',
                                                                startIndex: {{ $index }},
                                                                taskId: {{ $task->id }},
                                                                locationId: {{ $task->location_id ?? 'null' }},
                                                                currentRoom: currentRoom
                                                            })">
                                                        <img src="{{ Storage::url($photo->file_path) }}" alt="Voltooiingsfoto" class="rounded-md object-cover h-24 w-24 cursor-pointer hover:opacity-75 transition">
                                                    </button>
                                                    @endforeach
                                                </div>
                                        </div>
                                        @endif
                                    </div>
                                @elseif($completion->review_outcome === 'reopened')
                                    {{-- Special block for Reopened --}}
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
                                @else
                                    {{-- Regular completion entry with its own review --}}
                                    <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-sm">
                                        <div class="flex justify-between items-center mb-2">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                Ingediend door: {{ $completion->user->name }} op {{ $completion->created_at->format('d-m-Y \o\m H:i') }}
                                            </p>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $completion->is_fully_completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $completion->is_fully_completed ? 'Volledig voltooid' : 'Niet volledig voltooid' }}
                                            </span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap mb-4">{{ $completion->comment }}</p>

                                        @if($completion->photos->isNotEmpty())
                                            @php
                                                $completionPhotos = $completion->photos->map(fn($photo) => Storage::url($photo->file_path))->values()->all();
                                                $completionPhotoIds = $completion->photos->pluck('id')->values()->all();
                                            @endphp
                                            <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-2">Bijgevoegde foto's</h4>
                                            <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2" x-data='{
                                                completionPhotos: {{ json_encode($completionPhotos) }},
                                                photoIds: {{ json_encode($completionPhotoIds) }}
                                            }'>
                                                @foreach($completion->photos as $index => $photo)
                                                    <button type="button" class="focus:outline-none"
                                                            @click="$dispatch('open-image-modal', {
                                                                imageUrls: $data.completionPhotos,
                                                                photoIds: $data.photoIds,
                                                                photoType: 'completion',
                                                                startIndex: {{ $index }},
                                                                taskId: {{ $task->id }},
                                                                locationId: {{ $task->location_id ?? 'null' }},
                                                                currentRoom: currentRoom
                                                            })">
                                                        <img src="{{ Storage::url($photo->file_path) }}" alt="Voltooiingsfoto" class="rounded-md object-cover h-32 w-32 cursor-pointer hover:opacity-75 transition">
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Review block for the above submission --}}
                                    @if($completion->review_outcome)
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
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
