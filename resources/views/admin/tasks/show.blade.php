@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Taak Beoordelen: {{ $task->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-3">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Details</h3>
                            <dl class="mt-4 space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Titel</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $task->title }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Locatie</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $task->location }}</dd>
                                </div>
                                @if($task->planning)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Planning</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('plannings.show', $task->planning) }}" class="underline hover:text-blue-500 dark:hover:text-blue-400">
                                            {{ $task->planning->title }}
                                        </a>
                                    </dd>
                                </div>
                                @endif
                                @if($task->description)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Beschrijving</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $task->description }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                @if ($completion_history && $completion_history->count() > 0)
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Voltooiingsgeschiedenis</h3>
                        <div class="mt-4 space-y-6">
                            @foreach ($completion_history as $completion)
                                <div class="p-4 rounded-md border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                        <div class="flex items-center space-x-6">
                                            <span>Voltooid door: <strong class="font-medium text-gray-900 dark:text-gray-100">{{ $completion->user?->name ?? 'N/A' }}</strong></span>
                                            <span>Op: <strong class="font-medium text-gray-900 dark:text-gray-100">{{ $completion->created_at ? $completion->created_at->format('d-m-Y H:i') : 'N/A' }}</strong></span>
                                            @if($completion->task_duration_seconds)
                                                @php
                                                    $hours = floor($completion->task_duration_seconds / 3600);
                                                    $minutes = floor(($completion->task_duration_seconds % 3600) / 60);
                                                    $seconds = $completion->task_duration_seconds % 60;
                                                    $duration = $hours > 0 ? "{$hours}u {$minutes}m {$seconds}s" : "{$minutes}m {$seconds}s";
                                                @endphp
                                                <span>Duur: <strong class="font-medium text-blue-600 dark:text-blue-400">{{ $duration }}</strong></span>
                                            @endif
                                        </div>
                                        <div>
                                            @if ($completion->is_fully_completed)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                    Volledig voltooid
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                    Deels voltooid
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <hr class="my-4 border-gray-300 dark:border-gray-600">

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Opmerkingen:</h4>
                                            @if($completion->comment)
                                                <p class="mt-1 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $completion->comment }}</p>
                                            @else
                                                <p class="mt-1 text-sm italic text-gray-500 dark:text-gray-400">Geen opmerkingen voor deze poging.</p>
                                            @endif
                                        </div>

                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Foto's:</h4>
                                            @if ($completion->photos && $completion->photos->count() > 0)
                                                @php
                                                    $completionPhotos = $completion->photos->map(fn($photo) => \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path))->values()->all();
                                                    $completionPhotoIds = $completion->photos->pluck('id')->values()->all();
                                                    $completionPhotoRooms = $completion->photos->pluck('room')->values()->all();
                                                @endphp
                                                <div class="mt-2 grid grid-cols-3 sm:grid-cols-4 gap-2"
                                                     x-data='{
                                                        completionPhotos: {{ json_encode($completionPhotos) }},
                                                        photoIds: {{ json_encode($completionPhotoIds) }},
                                                        photoRooms: {{ json_encode($completionPhotoRooms) }},
                                                        onRoomLinked(detail) {
                                                            if (detail.photoType === "completion") {
                                                                const idx = $data.photoIds.indexOf(detail.photoId);
                                                                if (idx !== -1) {
                                                                    $data.photoRooms[idx] = detail.room;
                                                                }
                                                            }
                                                        }
                                                     }'
                                                     @room-linked.window="onRoomLinked($event.detail)">
                                                    @foreach ($completion->photos as $index => $photo)
                                                        <button type="button" class="focus:outline-none"
                                                                @click="$dispatch('open-image-modal', {
                                                                    imageUrls: $data.completionPhotos,
                                                                    photoIds: $data.photoIds,
                                                                    photoType: 'completion',
                                                                    startIndex: {{ $index }},
                                                                    taskId: {{ $task->id }},
                                                                    locationId: {{ $task->location_id ?? 'null' }},
                                                                    currentRooms: $data.photoRooms
                                                                })">
                                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path) }}" alt="Completion Photo" class="rounded-lg shadow-md hover:opacity-75 transition-opacity object-cover h-32 w-32">
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-1 text-sm italic text-gray-500 dark:text-gray-400">Geen foto's voor deze poging.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($type === 'end_checklist_item')
                    {{-- Special handling for end checklist items --}}
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-900/20">
                        <h3 class="text-lg font-medium text-purple-800 dark:text-purple-200 mb-4">📋 End Checklist Item Beoordeling</h3>
                        <p class="text-sm text-purple-700 dark:text-purple-300 mb-4">
                            Dit is een end checklist item dat door de medewerker is ingediend ter verificatie.
                        </p>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-purple-200 dark:border-purple-600 mb-4">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Item Details:</h4>
                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Type:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                                @if($task->checklist_type === 'material')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                        📦 Materiaal
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        ✅ Eind Actie
                                                    </span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Titel:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $task->title }}</dd>
                                        </div>
                                        @if($task->description)
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Beschrijving:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $task->description }}</dd>
                                        </div>
                                        @endif
                                        @if($task->specific_location)
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Specifieke Locatie:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $task->specific_location->name }}</dd>
                                        </div>
                                        @endif
                                        @if($task->uploader_name)
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Geüpload door:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                                {{ $task->uploader_name }}
                                                @if($task->uploaded_at)
                                                    <span class="text-xs text-gray-500">op {{ $task->uploaded_at->format('d-m-Y H:i') }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                        @endif
                                        @if(isset($task->related_items) && $task->related_items->count() > 1)
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Gerelateerde Items:</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                                @foreach($task->related_items as $related)
                                                    <div class="flex justify-between items-center py-1 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                                        <span>{{ $related->location?->name ?? 'Algemeen' }}</span>
                                                        <span class="text-xs">
                                                            @if($related->uploader)
                                                                {{ $related->uploader->name }}
                                                                @if($related->uploaded_at)
                                                                    ({{ $related->uploaded_at->format('d-m H:i') }})
                                                                @endif
                                                            @else
                                                                <span class="text-gray-400">Geen upload</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </dd>
                                        </div>
                                        @endif
                                    </dl>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Ingediende Foto:</h4>
                                    @php
                                        $photoId = null;
                                        $photoRoom = '';
                                        if ($type === 'task') {
                                            $latestPhoto = $task->planningTasks->flatMap->planningTaskPhotos->sortByDesc('created_at')->first();
                                            $photoId = $latestPhoto?->id;
                                            $photoRoom = $latestPhoto?->room ?? '';
                                        }
                                    @endphp
                                    @if($task->photo_url)
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden"
                                             x-data='{ currentRoom: "{{ $photoRoom }}" }'
                                             @room-linked.window="if($event.detail.photoId == {{ $photoId ?? 'null' }} && ($event.detail.photoType === 'task' || $event.detail.photoType === 'task_photo')) $data.currentRoom = $event.detail.room">
                                            <img src="{{ $task->photo_url }}"
                                                 alt="{{ $task->title }}"
                                                 class="w-full h-64 object-contain bg-gray-50 dark:bg-gray-700 cursor-pointer"
                                                 @click="$dispatch('open-image-modal', {
                                                    imageUrls: ['{{ $task->photo_url }}'],
                                                    photoIds: [{{ $photoId ?? 'null' }}],
                                                    photoType: '{{ $type === 'task' ? 'task' : 'task_photo' }}',
                                                    startIndex: 0,
                                                    taskId: {{ $task->id }},
                                                    locationId: {{ $task->location_id ?? 'null' }},
                                                    currentRooms: [$data.currentRoom]
                                                 })">
                                        </div>
                                    @else
                                        <p class="text-sm italic text-gray-500 dark:text-gray-400">Geen foto beschikbaar</p>
                                    @endif
                                </div>
                            </div>

                            @if($task->admin_notes)
                                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Eerdere admin opmerkingen:</h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $task->admin_notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Review actions (approve / reject via modal) --}}
                    <div class="p-6 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('admin.tasks.review') }}"
                               class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                Terug naar Review Overzicht
                            </a>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Reject opens modal -->
                            <x-danger-button
                                x-data
                                x-on:click.prevent="$dispatch('open-modal', 'reject-end-item-{{ $task->item->id }}')"
                                class="inline-flex items-center px-6 py-2.5 text-sm"
                            >Afkeuren</x-danger-button>

                            <!-- Approve posts (robust: Alpine store → window → native submit) -->
                            <form
                                x-on:submit.prevent="$store.endChecklistActions && $store.endChecklistActions.approve
                                    ? $store.endChecklistActions.approve($event, {{ $task->item->id }})
                                    : (window.approveEndChecklistItem
                                        ? window.approveEndChecklistItem($event, {{ $task->item->id }})
                                        : $event.currentTarget.submit())"
                                method="POST" action="{{ $task->approve_route }}">
                                @csrf
                                <x-primary-button type="submit" class="px-6 py-2.5 text-sm">Goedkeuren</x-primary-button>
                            </form>
                        </div>
                    </div>

                    <!-- Reject modal for End Checklist Item -->
                    <x-modal name="reject-end-item-{{ $task->item->id }}" :show="$errors->isNotEmpty()" focusable>
                        <form
                            x-on:submit.prevent="$store.endChecklistActions && $store.endChecklistActions.reject
                                ? $store.endChecklistActions.reject($event, {{ $task->item->id }})
                                : (window.rejectEndChecklistItem
                                    ? window.rejectEndChecklistItem($event, {{ $task->item->id }})
                                    : $event.currentTarget.submit())"
                            method="POST" action="{{ route('admin.end-checklist.reject.process', $task->item) }}" class="p-6">
                            @csrf

                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Afkeuren: {{ $task->title }}
                            </h2>

                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                @if($task->description)
                                    <p class="whitespace-pre-wrap">{{ $task->description }}</p>
                                @endif
                                @if($task->photo_url)
                                    <div class="mt-3">
                                        <img src="{{ $task->photo_url }}" alt="Checklist item foto" class="max-w-full h-40 object-contain rounded border border-gray-200 dark:border-gray-700">
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6">
                                <x-input-label for="admin_notes_{{ $task->item->id }}" value="{{ __('Reden voor afwijzing (verplicht)') }}" />
                                <textarea id="admin_notes_{{ $task->item->id }}" name="admin_notes" rows="4" required class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('admin_notes') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('admin_notes')" />
                            </div>

                            <div class="mt-4 flex items-start space-x-3">
                                <input type="hidden" name="create_new_task" value="0">
                                <input id="create_new_task_{{ $task->item->id }}" name="create_new_task" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" {{ old('create_new_task', '1') ? 'checked' : '' }}>
                                <label for="create_new_task_{{ $task->item->id }}" class="text-sm text-gray-700 dark:text-gray-300">
                                    Bij afwijzen: maak een nieuwe taak aan en neem reden en foto's over
                                </label>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-secondary-button x-on:click="$dispatch('close')">
                                    {{ __('Annuleren') }}
                                </x-secondary-button>
                                <x-danger-button type="submit" class="ml-3">
                                    {{ __('Definitief afkeuren') }}
                                </x-danger-button>
                            </div>
                        </form>
                    </x-modal>
                @elseif($type === 'skipped_planning_task')
                    {{-- Special handling for skipped tasks --}}
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-yellow-50 dark:bg-yellow-900/20">
                        <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200 mb-4">⚠️ Overgeslagen Taak Beoordeling</h3>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mb-4">
                            Deze taak is overgeslagen door de medewerker. Bepaal of deze taak opnieuw als kopie in de backlog moet worden toegevoegd.
                        </p>

                        @if($completion_history && $completion_history->count() > 0)
                            @php
                                $skipCompletion = $completion_history->where('review_outcome', 'skipped')->last();
                            @endphp
                            @if($skipCompletion)
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-yellow-200 dark:border-yellow-600 mb-4">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Reden voor overslaan:</h4>
                                    <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $skipCompletion->comment ?: 'Geen reden opgegeven' }}</p>

                                    @if($skipCompletion->photos && $skipCompletion->photos->count() > 0)
                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-4 mb-2">Foto's bij overslaan:</h4>
                                        @php
                                            $skipPhotos = $skipCompletion->photos->map(fn($photo) => \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path))->values()->all();
                                        @endphp
                                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" x-data='{ skipPhotos: {{ json_encode($skipPhotos) }} }'>
                                            @foreach ($skipCompletion->photos as $index => $photo)
                                                <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: skipPhotos, startIndex: {{ $index }}, photoIds: [], photoType: '{{ $type === 'task' ? 'task' : 'task_photo' }}', currentRooms: [] })">
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path) }}" alt="Skip Photo" class="rounded-lg shadow-md hover:opacity-75 transition-opacity object-cover h-24 w-24">
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>

                    <form id="skipped-review-form" method="POST" action="{{ route('admin.tasks.review-skipped', $task->item->id) }}">
                        @csrf
                        <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                            <label for="review_notes" class="block text-lg font-medium text-gray-900 dark:text-gray-100">Opmerking toevoegen (optioneel)</label>
                            <div class="mt-2">
                                <textarea id="review_notes" name="review_notes" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" placeholder="Voeg hier je opmerkingen toe over de beslissing..."></textarea>
                            </div>
                        </div>

                        <div class="p-6 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                            <div class="flex items-center justify-between">
                                @if ($planning)
                                    <a href="{{ route('plannings.show', $planning) }}"
                                       class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                        <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                        Terug naar Planning
                                    </a>
                                @endif
                            </div>
                            <div class="flex items-center space-x-4">
                                <button type="submit" name="action" value="dismiss" class="px-6 py-2.5 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                    Niet opnieuw toevoegen
                                </button>
                                <button type="submit" name="action" value="add_to_backlog" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Opnieuw toevoegen aan backlog
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    {{-- Regular review form for completed tasks --}}
                    <form id="review-form" method="POST">
                        @csrf
                        <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                            <label for="review_notes" class="block text-lg font-medium text-gray-900 dark:text-gray-100">Opmerkingen (verplicht bij afwijzing)</label>
                            <div class="mt-2">
                                <textarea id="review_notes" name="review_notes" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" placeholder="Voeg hier je opmerkingen voor de medewerker toe..."></textarea>
                            </div>
                            <div class="mt-4 flex items-start space-x-3">
                                <input type="hidden" name="create_replacement" value="0">
                                <input id="create_replacement_regular" name="create_replacement" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" {{ old('create_replacement', '1') ? 'checked' : '' }}>
                                <label for="create_replacement_regular" class="text-sm text-gray-700 dark:text-gray-300">
                                    Bij afwijzen: maak een nieuwe taak aan en neem reden en foto's over
                                </label>
                            </div>
                            @if ($planning)
                                <input type="hidden" name="planning_id" value="{{ $planning->id }}">
                            @endif
                        </div>

                        <div class="p-6 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                            <div class="flex items-center justify-between">
                                @if ($planning)
                                    <a href="{{ route('plannings.show', $planning) }}"
                                       class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                        <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                        Terug naar Planning
                                    </a>
                                @endif
                            </div>
                            <div class="flex items-center space-x-4">
                                <button type="submit" formaction="{{ $task->reject_route }}" class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Afkeuren
                                </button>
                                <button type="submit" formaction="{{ $task->approve_route }}" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Goedkeuren
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Foto Workflow Sectie --}}
                    @if($task->type === 'task' || $task->type === 'planning_task')
                        <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/10"
                             x-data="{
                                rooms: [],
                                loadingRooms: false,
                                roomsError: false,
                                selectedRoom: '{{ $task->room ?? '' }}',
                                async init() {
                                    this.loadingRooms = true;
                                    this.roomsError = false;
                                    try {
                                        const response = await fetch('{{ route('photo-workflow.rooms', ['task' => ($task->type === 'task' ? $task->item->id : $task->item->task_id ?? $task->item->id)]) }}');
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
                             }">
                            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-300">Niet verhuurde ruimte vol workflow</h3>
                            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">Gebruik dit formulier om de foto van de ruimte rond te sturen naar alle klanten en het automatische opvolgingsproces te starten.</p>

                            <form action="{{ route('photo-workflow.distribute', ['task' => ($task->type === 'task' ? $task->item->id : $task->item->task_id ?? $task->item->id)]) }}" method="POST" class="mt-4">
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
                                @if($task->item->photo_process_step)
                                    <div class="mt-3 text-xs text-blue-600 dark:text-blue-400">
                                        Huidige status: <strong>{{ $task->item->photo_process_step }}</strong>
                                        (Laatste update: {{ $task->item->photo_process_at ? $task->item->photo_process_at->format('d-m-Y H:i') : 'Onbekend' }})
                                    </div>
                                @endif
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
@push('scripts')
<script>
    (function(){
        // Robustly expose handlers to Alpine via a store to avoid scope/timing issues
        function registerEndChecklistActionsStore() {
            try {
                if (!window.Alpine) return false;
                if (typeof window.Alpine.store === 'function') {
                    const existing = (() => { try { return window.Alpine.store('endChecklistActions'); } catch(_) { return undefined; } })();
                    if (existing) return true;
                    window.Alpine.store('endChecklistActions', {
                        approve: function(){ return window.approveEndChecklistItem && window.approveEndChecklistItem.apply(this, arguments); },
                        reject: function(){ return window.rejectEndChecklistItem && window.rejectEndChecklistItem.apply(this, arguments); },
                    });
                    return true;
                }
            } catch(_) {}
            return false;
        }

        // 1) If Alpine is already on the page, register immediately
        registerEndChecklistActionsStore();
        // 2) Also register on Alpine init (covers deferred Alpine loading)
        document.addEventListener('alpine:init', registerEndChecklistActionsStore);
        // 3) As a final fallback, try again after window load and on next tick
        window.addEventListener('load', () => { registerEndChecklistActionsStore(); setTimeout(registerEndChecklistActionsStore, 0); });

        function csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'; }

        function showToast(message, type = 'success') {
            const container = document.createElement('div');
            container.className = 'fixed z-50 top-4 right-4 max-w-sm w-full';
            const bg = type === 'error' ? 'bg-red-600' : 'bg-green-600';
            container.innerHTML = `
                <div class="rounded-md ${bg} text-white shadow-lg ring-1 ring-black/5 overflow-hidden">
                    <div class="p-4 flex items-start">
                        <svg class="h-5 w-5 text-white/90 mt-0.5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div class="text-sm font-medium flex-1">${message}</div>
                        <button type="button" class="ml-3 text-white/80 hover:text-white focus:outline-none" aria-label="Sluiten">✕</button>
                    </div>
                </div>`;
            document.body.appendChild(container);
            const closeBtn = container.querySelector('button');
            const close = () => { container.remove(); };
            closeBtn.addEventListener('click', close);
            setTimeout(close, type === 'error' ? 5000 : 4000);
        }

        async function postJson(url, formData) {
            let resp;
            try {
                resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
            } catch (networkErr) {
                try { window.location.reload(); } catch(_) {}
                throw networkErr;
            }

            if (resp.redirected) {
                window.location.href = resp.url;
                return new Promise(() => {});
            }

            const contentType = resp.headers.get('content-type') || '';

            if (!resp.ok) {
                if (resp.status === 401 || resp.status === 419) {
                    window.location.reload();
                    return new Promise(() => {});
                }

                let msg = 'Onbekende fout.';
                if (contentType.includes('application/json')) {
                    try {
                        const data = await resp.json();
                        if (data?.message) msg = data.message;
                        if (data?.errors) {
                            const firstKey = Object.keys(data.errors)[0];
                            if (firstKey) msg = data.errors[firstKey][0] || msg;
                        }
                    } catch(_) {}
                } else {
                    try {
                        const text = await resp.text();
                        const titleMatch = text.match(/<title[^>]*>([^<]+)<\/title>/i);
                        if (titleMatch && titleMatch[1]) {
                            msg = titleMatch[1].trim();
                        } else {
                            const firstLine = text.split('\n').map(l => l.trim()).find(l => l);
                            if (firstLine) msg = firstLine.slice(0, 160);
                        }
                    } catch(_) {}
                }
                throw new Error(msg);
            }

            if (contentType.includes('application/json')) {
                return await resp.json();
            }

            window.location.reload();
            return new Promise(() => {});
        }

        window.approveEndChecklistItem = async function(e, itemId) {
            const form = e.currentTarget || (e.target && e.target.closest && e.target.closest('form'));
            const formData = new FormData(form);
            try {
                const data = await postJson(form.action, formData);
                showToast((data && (data.message || data.success && 'End checklist item goedgekeurd.')) || 'End checklist item goedgekeurd.');
                // If no redirect happened and JSON returned, we can update UI or reload for simplicity
                try { window.location.reload(); } catch(_) {}
            } catch (err) {
                showToast(err.message || 'Kon item niet goedkeuren.', 'error');
            }
        }

        window.rejectEndChecklistItem = async function(e, itemId) {
            const form = e.currentTarget || (e.target && e.target.closest && e.target.closest('form'));
            const formData = new FormData(form);
            try {
                const data = await postJson(form.action, formData);
                showToast((data && (data.message || data.success && 'End checklist item afgekeurd.')) || 'End checklist item afgekeurd.');
                try { window.location.reload(); } catch(_) {}
            } catch (err) {
                showToast(err.message || 'Kon item niet afkeuren.', 'error');
            }
        }
    })();
</script>
@endpush
