<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <!-- PWA Meta Tags -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#3b82f6">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="auth-user" content="{{ auth()->check() ? auth()->id() : '' }}">
        
        <!-- PWA Apple Touch Icons -->
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Planning App">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900" x-data x-init="$store.theme.init()">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
            
            <!-- Toast Notifications -->
            @if(session('success'))
                <div x-data="{ show: true }" 
                     x-show="show" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="transform opacity-0 translate-y-2"
                     x-transition:enter-end="transform opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="transform opacity-100 translate-y-0"
                     x-transition:leave-end="transform opacity-0 translate-y-2"
                     x-init="setTimeout(() => show = false, 5000)"
                     class="fixed top-4 right-4 z-50 max-w-sm w-full bg-green-600 text-white px-6 py-4 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm font-medium">{{ session('success') }}</span>
                        <button @click="show = false" class="ml-4 text-green-200 hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif
            
            @if(session('error'))
                <div x-data="{ show: true }" 
                     x-show="show" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="transform opacity-0 translate-y-2"
                     x-transition:enter-end="transform opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="transform opacity-100 translate-y-0"
                     x-transition:leave-end="transform opacity-0 translate-y-2"
                     x-init="setTimeout(() => show = false, 7000)"
                     class="fixed top-4 right-4 z-50 max-w-sm w-full bg-red-600 text-white px-6 py-4 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm font-medium">{{ session('error') }}</span>
                        <button @click="show = false" class="ml-4 text-red-200 hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif
            
            <!-- Offline Status Indicator -->
            <div x-data="offlineStatus()" 
                 x-init="init()"
                 class="fixed top-4 left-4 z-50">
                 
                <!-- Online status -->
                <div x-show="isOnline && pendingSync === 0" 
                     x-transition
                     class="bg-green-500 text-white px-3 py-2 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm font-medium">Online</span>
                    </div>
                </div>
                
                <!-- Offline status -->
                <div x-show="!isOnline" 
                     x-transition
                     class="bg-red-500 text-white px-3 py-2 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm font-medium">Offline</span>
                    </div>
                </div>
                
                <!-- Pending sync -->
                <div x-show="pendingSync > 0" 
                     x-transition
                     class="bg-orange-500 text-white px-3 py-2 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium" x-text="`${pendingSync} items te sync`"></span>
                    </div>
                </div>
            </div>
        </div>

        @stack('scripts')
        
        <script>
        function offlineStatus() {
            return {
                isOnline: navigator.onLine,
                pendingSync: 0,
                syncInProgress: false,
                
                init() {
                    // Listen to online/offline events
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        if (window.offlinePlanningManager) {
                            window.offlinePlanningManager.attemptSync();
                        }
                    });
                    
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });
                    
                    // Check pending sync count every 5 seconds
                    setInterval(async () => {
                        if (window.offlinePlanningManager) {
                            try {
                                const counts = await window.offlinePlanningManager.getPendingSyncCount();
                                this.pendingSync = counts.total;
                            } catch (error) {
                                console.error('Error getting pending sync count:', error);
                            }
                        }
                    }, 5000);
                    
                    // Listen to sync events
                    if (window.offlinePlanningManager) {
                        window.offlinePlanningManager.onSyncStatusChange((status) => {
                            this.syncInProgress = status.syncInProgress;
                        });
                    }
                }
            }
        }
        </script>
    </body>
</html>
