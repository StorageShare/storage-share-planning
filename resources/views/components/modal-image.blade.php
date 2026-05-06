@props(['imageUrls' => []])

<div
    x-data="{
        show: false,
        imageUrls: [],
        currentIndex: 0,
        photoType: 'task', // 'task' or 'completion'
        photoIds: [],
        currentRooms: [],
        currentLocationIds: [],
        planningTaskId: null,
        taskId: null,
        photoId: null,
        locationId: null,
        externalId: null,
        selectedRoom: '',
        rooms: [],
        allLocations: [], // Add allLocations to state
        loadingRooms: false,
        roomsError: false,
        isLinking: false,
        justLinked: false,
        tomSelectInstance: null,

        async init() {
            this.$watch('show', value => {
                if (!value) {
                    if (this.tomSelectInstance) {
                        this.tomSelectInstance.destroy();
                        this.tomSelectInstance = null;
                    }
                    this.planningTaskId = null;
                    this.taskId = null;
                    this.photoId = null;
                    this.photoIds = [];
                    this.currentRooms = [];
                    this.currentLocationIds = [];
                    this.locationId = null;
                    this.externalId = null;
                    this.selectedRoom = '';
                    this.rooms = [];
                    this.allLocations = [];
                }
            });

            this.$watch('currentIndex', value => {
                this.photoId = this.photoIds[value] || null;
                this.locationId = this.currentLocationIds[value] || null;
                this.selectedRoom = this.currentRooms[value] || '';
            });

            this.$watch('locationId', (value) => {
                if (value) {
                    this.rooms = []; // Clear current rooms when location changes
                    this.fetchRooms();
                }
            });
        },

        async fetchRooms() {
            if (!this.locationId || this.loadingRooms) return;

            this.loadingRooms = true;
            this.roomsError = false;
            try {
                const response = await axios.get(`/locations/${this.locationId}/rooms`);
                if (response.data && response.data.success) {
                    this.rooms = response.data.rooms;
                    this.$nextTick(() => {
                        this.initTomSelect();
                    });
                } else {
                    this.roomsError = true;
                    this.rooms = [];
                    this.initTomSelect(); // Re-init even if empty to clear previous options
                }
            } catch (e) {
                console.error('Error fetching rooms:', e);
                this.roomsError = true;
                this.rooms = [];
                this.initTomSelect();
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

            // Prepare options for TomSelect
            const options = this.rooms.map(room => ({ value: room, text: room }));

            this.tomSelectInstance = new TomSelect(selectEl, {
                create: true,
                maxItems: 1,
                placeholder: 'Selecteer of typ ruimte...',
                options: options,
                items: this.selectedRoom ? [this.selectedRoom] : [],
                onChange: (value) => {
                    this.selectedRoom = value;
                }
            });

            // Sync TomSelect when selectedRoom changes from outside (e.g. navigation)
            this.$watch('selectedRoom', (value) => {
                if (this.tomSelectInstance && this.tomSelectInstance.getValue() !== value) {
                    this.tomSelectInstance.setValue(value, true);
                }
            });
        },

        reApplySelectedRoom() {
            if (this.tomSelectInstance) {
                this.tomSelectInstance.setValue(this.selectedRoom, true);
                return;
            }
            // Re-apply selectedRoom to ensure it's picked up by the select element after rooms are loaded
            const current = this.selectedRoom;
            this.selectedRoom = '';
            this.$nextTick(() => {
                this.selectedRoom = current;
            });
        },

        async linkRoom() {
            if (!this.photoId || !this.selectedRoom || this.isLinking) {
                if (!this.photoId) console.warn('[ModalImage] linkRoom aborted: photoId is missing');
                if (!this.selectedRoom) console.warn('[ModalImage] linkRoom aborted: selectedRoom is missing');
                return;
            }
            this.isLinking = true;
            try {
                let url;
                if (this.photoType === 'completion') {
                    url = `/photo-workflow/completion-photos/${this.photoId}/link-room`;
                } else if (this.photoType === 'planning_completion') {
                    url = `/photo-workflow/planning-completion-photos/${this.photoId}/link-room`;
                } else if (this.photoType === 'planning_comment' || this.photoType === 'comment_photo') {
                    url = `/photo-workflow/comment-photos/${this.photoId}/link-room`;
                } else if (this.photoType === 'task_photo') {
                    url = `/photo-workflow/task-photos/${this.photoId}/link-room`;
                } else if (this.photoType === 'planning' || this.photoType === 'task') {
                    url = `/photo-workflow/photos/${this.photoId}/link-room`;
                } else {
                    url = `/photo-workflow/photos/${this.photoId}/link-room`;
                }

                console.info('[ModalImage] Linking room...', { url, room: this.selectedRoom });

                const response = await axios.post(url, {
                    room: this.selectedRoom,
                    location_id: this.locationId
                });

                // Update currentRooms and currentLocationIds locally
                if (this.currentIndex >= 0 && this.currentIndex < this.currentRooms.length) {
                    this.currentRooms[this.currentIndex] = this.selectedRoom;
                }
                if (this.currentIndex >= 0 && this.currentIndex < this.currentLocationIds.length) {
                    this.currentLocationIds[this.currentIndex] = this.locationId;
                }

                this.$dispatch('notify', { type: 'success', message: 'Locatie en ruimte succesvol gekoppeld.' });

                this.justLinked = true;
                setTimeout(() => {
                    this.justLinked = false;
                }, 3000);

                // Update the local state in the caller if possible
                this.$dispatch('room-linked', { photoId: this.photoId, photoType: this.photoType, room: this.selectedRoom, locationId: this.locationId });

            } catch (e) {
                console.error('Error linking room:', e);
                this.$dispatch('notify', { type: 'error', message: 'Fout bij koppelen ruimte.' });
            } finally {
                this.isLinking = false;
            }
        },

        async downloadCurrent() {
            try {
                const url = this.imageUrls[this.currentIndex];
                if (!url) return;
                // Try to infer a filename from the URL
                const urlObj = new URL(url, window.location.origin);
                const pathname = urlObj.pathname;
                const suggestedName = pathname.split('/').filter(Boolean).pop() || 'photo.jpg';

                const res = await fetch(url, { credentials: 'include' });
                const blob = await res.blob();
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = suggestedName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(blobUrl);
            } catch (e) {
                // Fallback: open in new tab so the user can save manually
                const url = this.imageUrls[this.currentIndex];
                if (url) window.open(url, '_blank');
            }
        },
        async copyCurrent() {
            const url = this.imageUrls[this.currentIndex];
            if (!url) return;

            const notify = (type, message) => {
                try { this.$dispatch('notify', { type, message }); } catch(_) { /* no-op */ }
            };

            // Fetch image blob first
            let blob;
            try {
                const res = await fetch(url, { credentials: 'include' });
                blob = await res.blob();
            } catch (e) {
                console.warn('[Clipboard] Fetch failed, falling back to URL copy', e);
                return await this.copyUrlFallback(url, e?.message || 'fetch failed');
            }

            const originalType = blob.type || 'image/jpeg';
            if (window.ClipboardItem && navigator.clipboard?.write) {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ [originalType]: blob })]);
                    notify('success', 'Foto gekopieerd naar klembord.');
                    try { console.info('[Clipboard] Image copied as binary', { type: originalType, size: `${(blob.size/1024).toFixed(1)} KB`, sourceUrl: url }); } catch(_) {}
                    return;
                } catch (e) {
                    console.warn('[Clipboard] JPEG/original write failed, trying PNG fallback…', { reason: e?.message });
                    // Try PNG fallback via canvas
                    try {
                        const pngBlob = await (async () => {
                            if (window.createImageBitmap) {
                                const bmp = await createImageBitmap(blob);
                                const canvas = document.createElement('canvas');
                                canvas.width = bmp.width; canvas.height = bmp.height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(bmp, 0, 0);
                                return new Promise((resolve, reject) => {
                                    canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob returned null')), 'image/png');
                                });
                            } else {
                                // Fallback to HTMLImageElement
                                const img = await new Promise((resolve, reject) => {
                                    const i = new Image();
                                    i.crossOrigin = 'anonymous';
                                    i.onload = () => resolve(i);
                                    i.onerror = reject;
                                    const objUrl = URL.createObjectURL(blob);
                                    i.src = objUrl;
                                });
                                const canvas = document.createElement('canvas');
                                canvas.width = img.naturalWidth || img.width; canvas.height = img.naturalHeight || img.height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0);
                                return new Promise((resolve, reject) => {
                                    canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob returned null')), 'image/png');
                                });
                            }
                        })();

                        await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob })]);
                        notify('success', 'Foto als PNG gekopieerd naar klembord.');
                        try { console.info('[Clipboard] Image copied as PNG fallback', { type: 'image/png', size: `${(pngBlob.size/1024).toFixed(1)} KB`, sourceUrl: url }); } catch(_) {}
                        return;
                    } catch (e2) {
                        console.warn('[Clipboard] PNG fallback failed, falling back to URL copy', { reason: e2?.message });
                        return await this.copyUrlFallback(url, e2?.message || 'png write failed');
                    }
                }
            }

            // If ClipboardItem API missing → fallback to URL
            return await this.copyUrlFallback(url, 'ClipboardItem not supported');
        }
    }"
    x-on:open-image-modal.window="
        show = true;
        imageUrls = $event.detail.imageUrls;
        photoIds = $event.detail.photoIds || [];
        photoType = $event.detail.photoType || 'task';
        currentIndex = $event.detail.startIndex || 0;
        photoId = photoIds[currentIndex] || null;
        planningTaskId = $event.detail.planningTaskId || null;
        taskId = $event.detail.taskId || null;
        allLocations = $event.detail.allLocations || [];
        currentLocationIds = $event.detail.currentLocationIds || [];
        locationId = currentLocationIds[currentIndex] || ($event.detail.locationId || null);
        currentRooms = $event.detail.currentRooms || [];
        selectedRoom = currentRooms[currentIndex] || ($event.detail.currentRoom || '');
        externalId = $event.detail.externalId || null;

        if (locationId) {
            fetchRooms();
        }

        $nextTick(() => $refs.modalPanel.focus());
    "
    x-on:keydown.escape.window="show = false"
    x-on:keydown.left.window="if(show && imageUrls.length > 1) { currentIndex = (currentIndex - 1 + imageUrls.length) % imageUrls.length; photoId = photoIds[currentIndex] || null; locationId = currentLocationIds[currentIndex] || null; selectedRoom = currentRooms[currentIndex] || ''; externalId = $event.detail.externalId || null; }"
    x-on:keydown.right.window="if(show && imageUrls.length > 1) { currentIndex = (currentIndex + 1) % imageUrls.length; photoId = photoIds[currentIndex] || null; locationId = currentLocationIds[currentIndex] || null; selectedRoom = currentRooms[currentIndex] || ''; externalId = $event.detail.externalId || null; }"
    x-show="show"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    <!-- Overlay -->
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="show = false"></div>

    <!-- Modal Panel -->
    <div x-ref="modalPanel"
         tabindex="-1"
         x-show="show"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-4xl max-h-[85vh] bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all flex flex-col focus:outline-none"
         @click.away="show = false">

        <div class="relative flex-grow flex items-center justify-center">
            <template x-for="(url, index) in imageUrls" :key="index">
                <div x-show="index === currentIndex" class="w-full h-full flex items-center justify-center p-2">
                    <img :src="url" alt="Volledige weergave" class="w-auto h-auto object-contain max-w-full max-h-[80vh]">
                </div>
            </template>
        </div>

        <!-- Action bar -->
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-center justify-between gap-3 z-20">
            <!-- Left side: Location and Room selection -->
            <div class="flex items-center gap-2 flex-grow max-w-2xl" x-show="((taskId || planningTaskId) && locationId) || photoType === 'planning_comment' || photoType === 'comment_photo'">
                @if(auth()->user()?->canExecutePlannings() || auth()->user()?->canTriggerPhotoWorkflow())
                    <div class="flex flex-wrap items-center gap-2 w-full">
                        <!-- Location selection -->
                        <div class="w-48" x-show="photoType === 'planning_comment' || photoType === 'comment_photo' || !locationId">
                            <select x-model="locationId"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-xs py-1.5">
                                <option value="">Selecteer locatie...</option>
                                <template x-for="loc in allLocations" :key="loc.id">
                                    <option :value="loc.id" x-text="loc.name" :selected="loc.id == locationId"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Room selection -->
                        <div x-show="locationId" class="flex items-center gap-2 flex-grow max-w-sm">
                            <div class="w-full text-gray-900" :class="justLinked ? 'ring-2 ring-green-500 rounded-md transition-all duration-300' : ''">
                                <select x-ref="roomSelect"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-xs py-1.5">
                                </select>
                            </div>
                            <button type="button"
                                    @click="linkRoom()"
                                    :disabled="!selectedRoom || !locationId || isLinking || justLinked"
                                    :class="justLinked ? 'bg-green-600' : ((!selectedRoom || !locationId || isLinking) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700')"
                                    class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-300 whitespace-nowrap">
                                <span x-show="!isLinking && !justLinked">Koppel</span>
                                <span x-show="isLinking">...</span>
                                <span x-show="justLinked" class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Gekoppeld
                                </span>
                            </button>
                        </div>
                        <template x-if="locationId && loadingRooms">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Ruimtes laden...</span>
                        </template>
                    </div>
                @endif
            </div>

            <!-- Right side: General actions -->
            <div class="flex items-center justify-end gap-2 ml-auto">
                @if(auth()->user()?->canTriggerPhotoWorkflow())
                    <template x-if="(taskId || externalId || (photoType === 'planning_completion' && planningTaskId)) && selectedRoom">
                        <form :action="photoType === 'planning_completion' && planningTaskId ? `/planning-tasks/${planningTaskId}/distribute` : (taskId ? `/tasks/${taskId}/distribute` : `/external/${externalId}/distribute`)" method="POST" class="inline-block">
                            @csrf
                            <input type="hidden" name="room" :value="selectedRoom">
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Foto rondsturen
                            </button>
                        </form>
                    </template>

                    <form x-show="!taskId && !externalId && (photoType === 'planning_comment' || photoType === 'comment_photo') && photoId && selectedRoom" :action="`/comment-photos/${photoId}/distribute`" method="POST" class="inline-block">
                        @csrf
                        <input type="hidden" name="room" :value="selectedRoom">
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Foto rondsturen
                        </button>
                    </form>
                @endif
                <button type="button"
                        @click.stop="downloadCurrent()"
                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"/><path d="M5 15a1 1 0 011 1v2a1 1 0 001 1h10a1 1 0 001-1v-2a1 1 0 112 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a1 1 0 011-1z"/></svg>
                    Download
                </button>
                <button type="button"
                        @click.stop="copyCurrent()"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs font-semibold rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M8 7a3 3 0 013-3h6a3 3 0 013 3v8a3 3 0 01-3 3h-2v-2h2a1 1 0 001-1V7a1 1 0 00-1-1h-6a1 1 0 00-1 1v2H8a1 1 0 00-1 1v8a1 1 0 001 1h2v2H8a3 3 0 01-3-3V10a3 3 0 013-3z"/><path d="M12 11a1 1 0 011-1h6a1 1 0 011 1v10a1 1 0 01-1 1h-6a1 1 0 01-1-1V11z"/></svg>
                    Kopieer
                </button>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <template x-if="imageUrls.length > 1">
            <div class="absolute inset-0 flex items-center justify-between px-4 z-10 pointer-events-none">
                <button @click.stop="currentIndex = (currentIndex - 1 + imageUrls.length) % imageUrls.length; photoId = photoIds[currentIndex] || null; locationId = currentLocationIds[currentIndex] || null; selectedRoom = currentRooms[currentIndex] || '';" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none pointer-events-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <button @click.stop="currentIndex = (currentIndex + 1) % imageUrls.length; photoId = photoIds[currentIndex] || null; locationId = currentLocationIds[currentIndex] || null; selectedRoom = currentRooms[currentIndex] || '';" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none pointer-events-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </template>

        <button @click="show = false" class="absolute -top-3 -right-3 p-1 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
