<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mediabibliotheek') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="mediaLibrary({
        initialLocationId: '{{ $locationId }}',
        initialRoom: '{{ $room }}',
        allLocations: {{ json_encode($locations->map(fn($l) => ['id' => $l->id, 'name' => $l->name])) }}
    })">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <!-- Filters -->
                    <form action="{{ route('media-library.index') }}" method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <x-input-label for="location_id" :value="__('Locatie')" />
                            <select name="location_id" id="location_id"
                                    x-model="locationId"
                                    @change="onLocationChange"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">{{ __('Alle locaties') }}</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ $locationId == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="room" :value="__('Ruimte')" />
                            <div class="relative">
                                <select name="room" id="room"
                                        x-model="selectedRoom"
                                        x-ref="roomSelect"
                                        :disabled="!locationId || loadingRooms"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm disabled:opacity-50">
                                    <option value="">{{ __('Alle ruimtes') }}</option>
                                    <template x-for="room in rooms" :key="room">
                                        <option :value="room" x-text="room" :selected="room === '{{ $room }}'"></option>
                                    </template>
                                    @if($room)
                                        <option value="{{ $room }}" selected>{{ $room }}</option>
                                    @endif
                                </select>
                                <div x-show="loadingRooms" class="absolute right-8 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="per_page" :value="__('Aantal per pagina')" />
                            <select name="per_page" id="per_page" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach([12, 24, 48, 96] as $level)
                                    <option value="{{ $level }}" {{ $perPage == $level ? 'selected' : '' }}>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <x-primary-button type="submit" @click="if(tomSelectInstance) selectedRoom = tomSelectInstance.getValue()">
                                {{ __('Filteren') }}
                            </x-primary-button>

                            @if($locationId || $room || $perPage != 24)
                                <a href="{{ route('media-library.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                    {{ __('Wissen') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    <!-- Photo Grid -->
                    @if($photos->isEmpty())
                        <div class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">{{ __('Geen foto\'s gevonden.') }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            @foreach($photos as $photo)
                                <div class="relative group bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden cursor-pointer shadow-sm hover:shadow-md transition-shadow"
                                     style="aspect-ratio: 1 / 1;"
                                     x-data="{ imageStatus: 'loading' }"
                                     @click="openModal('{{ Storage::url($photo->file_path) }}', {{ $photo->id }}, '{{ $photo->planning_task_id ?? '' }}', '{{ $photo->location_id }}', '{{ $photo->room }}', '{{ $photo->type }}')">
                                    <div x-show="imageStatus !== 'loaded'" class="absolute inset-0 z-0 flex flex-col items-center justify-center gap-2 bg-gray-100 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                        <svg x-show="imageStatus === 'loading'" class="h-7 w-7 animate-spin text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg x-show="imageStatus === 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10 text-gray-400 dark:text-gray-500">
                                            <path fill-rule="evenodd" d="M1.5 6A2.25 2.25 0 013.75 3.75h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zm1.5 10.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="px-2 text-center text-xs font-medium leading-tight" x-text="imageStatus === 'error' ? '{{ __('Niet beschikbaar') }}' : '{{ __('Laden...') }}'"></span>
                                    </div>
                                    <img src="{{ Storage::url($photo->file_path) }}"
                                         alt="Task photo"
                                         x-on:load="imageStatus = 'loaded'"
                                         x-on:error="imageStatus = 'error'"
                                         :class="imageStatus === 'loaded' ? 'opacity-100' : 'opacity-0'"
                                         class="relative z-10 w-full h-full object-cover transition-opacity duration-150">

                                    <div class="absolute inset-0 z-20 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-2" style="background-color: rgba(0, 0, 0, 0.5);">
                                        <div class="text-white text-[10px] leading-tight transition-opacity">
                                            <p class="font-bold truncate">
                                                {{ $photo->location_name }}
                                            </p>
                                            <p class="truncate">{{ __('Ruimte') }}: {{ $photo->room ?? '-' }}</p>
                                            <p class="truncate">{{ \Carbon\Carbon::parse($photo->created_at)->format('d-m-Y H:i') }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $photos->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-modal-image />

    <script>
        function mediaLibrary(config) {
            return {
                locationId: config.initialLocationId || '',
                selectedRoom: config.initialRoom || '',
                rooms: [],
                loadingRooms: false,
                tomSelectInstance: null,

                init() {
                    if (this.locationId) {
                        this.fetchRooms();
                    } else {
                        // Initialize TomSelect even without location to show the placeholder
                        this.$nextTick(() => {
                            this.initTomSelect();
                        });
                    }

                    this.$watch('locationId', (value) => {
                        this.selectedRoom = '';
                        if (this.tomSelectInstance) {
                            this.tomSelectInstance.clear();
                            this.tomSelectInstance.clearOptions();
                        }
                        this.rooms = [];
                        if (value) {
                            this.fetchRooms();
                        }
                    });
                },

                allLocations: config.allLocations || [],

                onLocationChange() {
                    // Handled by watcher now
                },

                async fetchRooms() {
                    this.loadingRooms = true;
                    try {
                        const response = await axios.get(`/locations/${this.locationId}/rooms`);
                        if (response.data && response.data.success) {
                            this.rooms = response.data.rooms;
                            this.$nextTick(() => {
                                this.initTomSelect();
                            });
                        }
                    } catch (e) {
                        console.error('Error fetching rooms:', e);
                    } finally {
                        this.loadingRooms = false;
                    }
                },

                initTomSelect() {
                    const selectEl = this.$refs.roomSelect;
                    if (!selectEl) return;

                    if (this.tomSelectInstance) {
                        this.tomSelectInstance.destroy();
                    }

                    const options = this.rooms.map(room => ({ value: room, text: room }));

                    this.tomSelectInstance = new TomSelect(selectEl, {
                        create: true,
                        maxItems: 1,
                        placeholder: '{{ __("Selecteer of typ ruimte...") }}',
                        options: options,
                        items: this.selectedRoom ? [this.selectedRoom] : [],
                        allowEmptyOption: true,
                        dropdownParent: 'body',
                        onChange: (value) => {
                            this.selectedRoom = value;
                        }
                    });
                },

                openModal(url, photoId, taskId, locationId, room, type) {
                    this.$dispatch('open-image-modal', {
                        imageUrls: [url],
                        photoIds: [photoId],
                        photoType: type,
                        startIndex: 0,
                        taskId: taskId,
                        locationId: locationId,
                        currentRoom: room,
                        allLocations: this.allLocations
                    });
                }
            }
        }
    </script>
</x-app-layout>
