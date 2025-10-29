<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ asset('images/beeldmerk-blue-dark.png') }}" alt="Logo" class="block dark:hidden h-9 w-auto">
                        <img src="{{ asset('images/beeldmerk-wit.png') }}" alt="Logo" class="hidden dark:block h-9 w-auto">
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if (Auth::user()->canExecutePlannings())
                        <x-nav-link :href="route('my-planning.show')" :active="request()->routeIs('my-planning.*')">
                            {{ __('Mijn Planning') }}
                        </x-nav-link>
                    @endif

                    @if (Auth::user()->canViewBacklog())
                        <x-nav-link :href="route('backlog.index')" :active="request()->routeIs('backlog.*')">
                            {{ __('Taken') }}
                        </x-nav-link>
                    @endif

                    @if (Auth::user()->canManagePlannings())
                        <x-nav-link :href="route('plannings.index')" :active="request()->routeIs('plannings.*')">
                            {{ __('Planningen') }}
                        </x-nav-link>
                    @endif

                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('plannings.review')" :active="request()->routeIs('plannings.review') || request()->routeIs('admin.tasks.*')">
                            {{ __('Te Beoordelen') }}
                        </x-nav-link>

                        <!-- Configuratie Dropdown -->
                        <div class="relative inline-flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150 {{ request()->routeIs(['locations.*', 'default-tasks.*', 'benodigdheden.*', 'users.*']) ? 'text-gray-900 dark:text-gray-100' : '' }}">
                                        <div>{{ __('Configuratie') }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('locations.index')">
                                        🏢 {{ __('Locaties') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('default-tasks.index')">
                                        📋 {{ __('Standaardtaken') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('benodigdheden.index')">
                                        🔧 {{ __('Benodigdheden') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('users.index')">
                                        👥 {{ __('Gebruikers') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('csv-import.index')">
                                        📤 {{ __('CSV Import') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>

                        <!-- Statistieken Dropdown -->
                        <div class="relative inline-flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150 {{ request()->routeIs(['admin.timers.*', 'admin.bv-stats.*']) ? 'text-gray-900 dark:text-gray-100' : '' }}">
                                        <div>{{ __('Statistieken') }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('admin.timers.index')">
                                        ⏱️ {{ __('Timer Overzicht') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.bv-stats.index')">
                                        📊 {{ __('BV Statistieken') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.logs.syslog')">
                                        📋 {{ __('Syslog Viewer') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>
                                <div>{{ Auth::user()->name }} ({{ match(Auth::user()->role->value) {
                                'admin' => 'Administrator',
                                'algemeen_medewerker' => 'Algemeen Medewerker',
                                'gebruiker' => 'Gebruiker',
                                'customer_service' => 'Klantenservice',
                                default => ucfirst(Auth::user()->role->value),
                            } }})</div>

                                <!-- Desktop Offline Status Indicator -->
                                <div x-data="offlineStatus()"
                                     x-init="init()"
                                     class="mt-1">

                                    <!-- Online status -->
                                    <div x-show="isOnline && pendingSync === 0"
                                         x-transition
                                         class="flex items-center text-green-600 dark:text-green-400">
                                        <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Online</span>
                                    </div>

                                    <!-- Offline status -->
                                    <div x-show="!isOnline"
                                         x-transition
                                         class="flex items-center text-red-600 dark:text-red-400">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-xs font-medium">Offline</span>
                                    </div>

                                    <!-- Pending sync -->
                                    <div x-show="pendingSync > 0"
                                         x-transition
                                         class="flex items-center text-orange-600 dark:text-orange-400">
                                        <svg class="animate-spin -ml-1 mr-2 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                          </svg>
                                        <span class="text-xs font-medium" x-text="`${pendingSync} items`"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-gray-400">
                            Thema
                        </div>

                        <button
                            x-on:click="$store.theme.toggle()"
                            type="button"
                            class="flex w-full items-center justify-start px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                        >
                            <span x-show="!$store.theme.darkMode">
                                @include('icons.moon')
                            </span>
                            <span x-show="$store.theme.darkMode">
                                @include('icons.sun')
                            </span>
                            <span class="ms-2" x-show="!$store.theme.darkMode">Donker</span>
                            <span class="ms-2" x-show="$store.theme.darkMode">Licht</span>
                        </button>

                        <div class="border-t border-gray-200 dark:border-gray-600"></div>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                🚪 {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Mobile: Offline Status + Hamburger -->
            <div class="flex items-center sm:hidden space-x-2">
                <!-- Mobile Offline Status Indicator -->
                <div x-data="offlineStatus()"
                     x-init="init()">

                    <!-- Online status - klein groen bolletje -->
                    <div x-show="isOnline && pendingSync === 0"
                         x-transition
                         class="bg-green-500 rounded-full p-1 shadow-md">
                        <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>

                    <!-- Offline status - rood bolletje -->
                    <div x-show="!isOnline"
                         x-transition
                         class="bg-red-500 text-white px-1 py-0.5 rounded-full shadow-md">
                        <div class="flex items-center">
                            <svg class="w-2 h-2 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs">Off</span>
                        </div>
                    </div>

                    <!-- Pending sync - oranje met aantal -->
                    <div x-show="pendingSync > 0"
                         x-transition
                         class="bg-orange-500 text-white px-1 py-0.5 rounded-full shadow-md">
                        <div class="flex items-center">
                            <svg class="animate-spin h-2 w-2 mr-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                              </svg>
                            <span class="text-xs font-medium" x-text="pendingSync"></span>
                        </div>
                    </div>
                </div>

                <!-- Hamburger -->
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @if (Auth::user()->canManagePlannings())
                <x-responsive-nav-link :href="route('plannings.index')" :active="request()->routeIs('plannings.*')">
                    {{ __('Planningen') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.tasks.review')" :active="request()->routeIs('admin.tasks.review') || request()->routeIs('admin.tasks.show')">
                    {{ __('Te Beoordelen') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('backlog.index')" :active="request()->routeIs('backlog.*')">
                    {{ __('Taken') }}
                </x-responsive-nav-link>

                <!-- Configuratie sectie -->
                <div class="px-4 py-2 text-xs text-gray-400 font-semibold uppercase tracking-wider">
                    Configuratie
                </div>
                <x-responsive-nav-link :href="route('locations.index')" :active="request()->routeIs('locations.*')">
                    🏢 {{ __('Locaties') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('default-tasks.index')" :active="request()->routeIs('default-tasks.*')">
                    📋 {{ __('Standaardtaken') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('benodigdheden.index')" :active="request()->routeIs('benodigdheden.*')">
                    🔧 {{ __('Benodigdheden') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    👥 {{ __('Gebruikers') }}
                </x-responsive-nav-link>

                <!-- Statistieken sectie -->
                <div class="px-4 py-2 text-xs text-gray-400 font-semibold uppercase tracking-wider">
                    Statistieken
                </div>
                <x-responsive-nav-link :href="route('admin.timers.index')" :active="request()->routeIs('admin.timers.*')">
                    ⏱️ {{ __('Timer Overzicht') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.bv-stats.index')" :active="request()->routeIs('admin.bv-stats.*')">
                    📊 {{ __('BV Statistieken') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }} ({{ \Illuminate\Support\Str::title(Auth::user()->role->value) }})</div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        🚪 {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
