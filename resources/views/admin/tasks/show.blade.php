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
                                                    $completionPhotos = $completion->photos->map(fn($photo) => Storage::url($photo->file_path))->values()->all();
                                                @endphp
                                                <div class="mt-2 grid grid-cols-3 sm:grid-cols-4 gap-2" x-data='{ completionPhotos: @json($completionPhotos) }'>
                                                    @foreach ($completion->photos as $index => $photo)
                                                        <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: completionPhotos, startIndex: {{ $index }} })">
                                                            <img src="{{ Storage::url($photo->file_path) }}" alt="Completion Photo" class="rounded-lg shadow-md hover:opacity-75 transition-opacity object-cover h-32 w-32">
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
                                    @if($task->photo_url)
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                            <img src="{{ $task->photo_url }}" 
                                                 alt="{{ $task->title }}" 
                                                 class="w-full h-64 object-contain bg-gray-50 dark:bg-gray-700 cursor-pointer"
                                                 @click="$dispatch('open-image-modal', { imageUrls: ['{{ $task->photo_url }}'], startIndex: 0 })">
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
                    
                    {{-- End checklist review form --}}
                    <form id="checklist-review-form" method="POST">
                        @csrf
                        <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                            <label for="admin_notes" class="block text-lg font-medium text-gray-900 dark:text-gray-100">Opmerkingen (verplicht bij afwijzing)</label>
                            <div class="mt-2">
                                <textarea id="admin_notes" name="admin_notes" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" placeholder="Voeg hier je opmerkingen toe...">{{ old('admin_notes') }}</textarea>
                            </div>
                        </div>
                        
                        <div class="p-6 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                            <div class="flex items-center justify-between">
                                <a href="{{ route('admin.tasks.review') }}"
                                   class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                    <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                    Terug naar Review Overzicht
                                </a>
                            </div>
                            <div class="flex items-center space-x-4">
                                <a href="{{ $task->reject_route }}" class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Afwijzen
                                </a>
                                <button type="submit" formaction="{{ $task->approve_route }}" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Goedkeuren
                                </button>
                            </div>
                        </div>
                    </form>
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
                                            $skipPhotos = $skipCompletion->photos->map(fn($photo) => Storage::url($photo->file_path))->values()->all();
                                        @endphp
                                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" x-data='{ skipPhotos: @json($skipPhotos) }'>
                                            @foreach ($skipCompletion->photos as $index => $photo)
                                                <button type="button" class="focus:outline-none" @click="$dispatch('open-image-modal', { imageUrls: skipPhotos, startIndex: {{ $index }} })">
                                                    <img src="{{ Storage::url($photo->file_path) }}" alt="Skip Photo" class="rounded-lg shadow-md hover:opacity-75 transition-opacity object-cover h-24 w-24">
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
                            <label for="review_notes" class="block text-lg font-medium text-gray-900 dark:text-gray-100">Opmerking toevoegen (optioneel)</label>
                            <div class="mt-2">
                                <textarea id="review_notes" name="review_notes" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" placeholder="Voeg hier je opmerkingen voor de medewerker toe..."></textarea>
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
                                <a href="{{ $task->reject_route }}" class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Afkeuren
                                </a>
                                <button type="submit" formaction="{{ $task->approve_route }}" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Goedkeuren
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <x-modal-image />
</x-app-layout> 