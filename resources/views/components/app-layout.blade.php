<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel Task Planner') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Preline UI Navigation -->
        <header class="flex flex-wrap sm:justify-start sm:flex-nowrap z-50 w-full bg-white border-b border-gray-200 text-sm py-3 sm:py-0">
            <nav class="relative max-w-full w-full mx-auto px-4 sm:flex sm:items-center sm:justify-between sm:px-6 lg:px-8" aria-label="Global">
                <div class="flex items-center justify-between">
                    <a class="flex-none text-xl font-semibold" href="{{ route('dashboard') }}" aria-label="Brand">{{ config('app.name', 'Task Planner') }}</a>
                    <div class="sm:hidden">
                        <button type="button" class="hs-collapse-toggle p-2 inline-flex justify-center items-center gap-x-2 rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none" data-hs-collapse="#navbar-collapse-with-animation" aria-controls="navbar-collapse-with-animation" aria-label="Toggle navigation">
                            <svg class="hs-collapse-open:hidden flex-shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
                            <svg class="hs-collapse-open:block hidden flex-shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div id="navbar-collapse-with-animation" class="hs-collapse hidden overflow-hidden transition-all duration-300 basis-full grow sm:block">
                    <div class="flex flex-col gap-y-4 gap-x-0 mt-5 sm:flex-row sm:items-center sm:justify-end sm:gap-y-0 sm:gap-x-7 sm:mt-0 sm:ps-7">
                        <a class="font-medium {{ request()->routeIs('dashboard') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('dashboard') }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : '' }}">Dashboard</a>
                        <a class="font-medium {{ request()->routeIs('locations.*') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('locations.index') }}">Locaties</a>
                        <a class="font-medium {{ request()->routeIs('default-tasks.*') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('default-tasks.index') }}">Standaard Taken</a>
                        <a class="font-medium {{ request()->routeIs('plannings.*') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('plannings.index') }}">Planningen</a>
                        <a class="font-medium {{ request()->routeIs('backlog.index') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('backlog.index') }}">Taken</a>
                        <a class="font-medium {{ request()->routeIs('external-backlog.*') ? 'text-blue-600 sm:py-6' : 'text-gray-500 hover:text-gray-400 sm:py-6' }}" href="{{ route('external-backlog.index') }}">Externe taken</a>
                    </div>
                </div>
            </nav>
        </header>

        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-white shadow">
                <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main class="py-12">
            <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-md sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{-- Preline Success Alert --}}
                        @if (session('success'))
                        <div class="bg-green-50 border border-green-400 text-sm text-green-700 p-4 rounded-md mb-4" role="alert">
                          <div class="flex">
                            <div class="flex-shrink-0">
                              <svg class="flex-shrink-0 size-4 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path>
                                <path d="m9 12 2 2 4-4"></path>
                              </svg>
                            </div>
                            <div class="ms-3">
                              <p class="font-medium">
                                {{ session('success') }}
                              </p>
                            </div>
                          </div>
                        </div>
                        @endif

                        {{-- Preline Error Alert --}}
                        @if (session('error'))
                        <div class="bg-red-50 border border-red-400 text-sm text-red-700 p-4 rounded-md mb-4" role="alert">
                          <div class="flex">
                            <div class="flex-shrink-0">
                              <svg class="flex-shrink-0 size-4 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" x2="12" y1="8" y2="12"></line>
                                <line x1="12" x2="12.01" y1="16" y2="16"></line>
                              </svg>
                            </div>
                            <div class="ms-3">
                              <p class="font-medium">
                                {{ session('error') }}
                              </p>
                            </div>
                          </div>
                        </div>
                        @endif

                        {{ $slot }}
                    </div>
                </div>
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html> 
