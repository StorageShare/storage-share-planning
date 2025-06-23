@props(['imageUrls' => []])

<div
    x-data="{ 
        show: false, 
        imageUrls: [], 
        currentIndex: 0 
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

        <!-- Navigation Buttons -->
        <template x-if="imageUrls.length > 1">
            <div class="absolute inset-0 flex items-center justify-between px-4">
                <button @click.stop="currentIndex = (currentIndex - 1 + imageUrls.length) % imageUrls.length" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <button @click.stop="currentIndex = (currentIndex + 1) % imageUrls.length" class="p-2 text-white bg-black bg-opacity-30 rounded-full hover:bg-opacity-50 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </template>
        
        <button @click="show = false" class="absolute -top-3 -right-3 p-1 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 z-10">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div> 