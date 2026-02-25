@props(['imageUrls' => []])

<div
    x-data="{
        show: false,
        imageUrls: [],
        currentIndex: 0,
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
            try {
                const url = this.imageUrls[this.currentIndex];
                if (!url || !navigator.clipboard) throw new Error('Clipboard not available');
                const res = await fetch(url, { credentials: 'include' });
                const blob = await res.blob();
                const type = blob.type || 'image/png';
                if (window.ClipboardItem) {
                    const item = new ClipboardItem({ [type]: blob });
                    await navigator.clipboard.write([item]);
                    this.$dispatch('notify', { type: 'success', message: 'Foto gekopieerd naar klembord.' });
                    // Developer feedback in console: confirm binary image copied
                    try {
                        const sizeKb = (blob.size / 1024).toFixed(1);
                        console.info('[Clipboard] Image copied as binary', { type, size: `${sizeKb} KB`, sourceUrl: url });
                    } catch(_) { /* no-op */ }
                } else {
                    throw new Error('ClipboardItem not supported');
                }
            } catch (e) {
                // Fallback: copy a public, absolute URL instead of the image binary
                const url = this.imageUrls[this.currentIndex];
                if (url && navigator.clipboard?.writeText) {
                    // Ensure we copy a full absolute URL so it can be shared publicly
                    let publicUrl;
                    try {
                        publicUrl = new URL(url, window.location.origin).href;
                    } catch(_) {
                        publicUrl = url; // best effort
                    }

                    await navigator.clipboard.writeText(publicUrl);
                    this.$dispatch('notify', { type: 'success', message: 'Publieke link naar foto gekopieerd.' });
                    // Developer feedback in console: only URL copied (absolute)
                    console.warn('[Clipboard] Copied URL instead of image (browser limitation or permission)', { publicUrl, reason: e?.message || 'unknown' });
                } else {
                    alert('Kopiëren niet ondersteund door deze browser.');
                    console.error('[Clipboard] Copy failed: no clipboard API available');
                }
            }
        }
    }"
    x-on:open-image-modal.window="
        show = true;
        imageUrls = $event.detail.imageUrls;
        currentIndex = $event.detail.startIndex || 0;
        $nextTick(() => $refs.modalPanel.focus());
    "
    x-on:keydown.escape.window="show = false"
    x-on:keydown.left.window="if(show && imageUrls.length > 1) currentIndex = (currentIndex - 1 + imageUrls.length) % imageUrls.length;"
    x-on:keydown.right.window="if(show && imageUrls.length > 1) currentIndex = (currentIndex + 1) % imageUrls.length;"
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
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex items-center justify-end gap-2 z-20">
            <button type="button"
                    @click.stop="downloadCurrent()"
                    class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-xs font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"/><path d="M5 15a1 1 0 011 1v2a1 1 0 001 1h10a1 1 0 001-1v-2a1 1 0 112 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a1 1 0 011-1z"/></svg>
                Download foto
            </button>
            <button type="button"
                    @click.stop="copyCurrent()"
                    class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs font-semibold rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2"><path d="M8 7a3 3 0 013-3h6a3 3 0 013 3v8a3 3 0 01-3 3h-2v-2h2a1 1 0 001-1V7a1 1 0 00-1-1h-6a1 1 0 00-1 1v2H8a1 1 0 00-1 1v8a1 1 0 001 1h2v2H8a3 3 0 01-3-3V10a3 3 0 013-3z"/><path d="M12 11a1 1 0 011-1h6a1 1 0 011 1v10a1 1 0 01-1 1h-6a1 1 0 01-1-1V11z"/></svg>
                Kopieer foto
            </button>
        </div>

        <!-- Navigation Buttons -->
        <template x-if="imageUrls.length > 1">
            <div class="absolute inset-0 flex items-center justify-between px-4 z-10 pointer-events-none">
                <button @click.stop="currentIndex = (currentIndex - 1 + imageUrls.length) % imageUrls.length" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none pointer-events-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <button @click.stop="currentIndex = (currentIndex + 1) % imageUrls.length" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none pointer-events-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </template>

        <button @click="show = false" class="absolute -top-3 -right-3 p-1 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
