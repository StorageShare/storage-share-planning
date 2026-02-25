<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Jouw Planning - {{ $planning->planned_date->format('d-m-Y') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div x-data="locationPlanning"
                data-location-steps='{{ json_encode($locationSteps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}'
                data-planning-id="{{ $planning->id }}"
                x-init="init($root)">

                {{-- Collapsed Summary (visible on steps after summary) --}}
                <div x-show="shouldShowCollapsedSummary()" class="bg-gray-50 dark:bg-gray-700 overflow-hidden shadow-sm sm:rounded-lg mb-4" x-data="{ summaryExpanded: false }">
                    <div class="p-4">
                        <button @click="summaryExpanded = !summaryExpanded" class="flex items-center justify-between w-full text-left">
                            <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">Samenvatting planning</h4>
                            <svg class="w-5 h-5 transform transition-transform" :class="summaryExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div x-show="summaryExpanded" x-transition class="mt-4">
                            @include('my-planning.partials.summary-content')
                        </div>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <template x-if="currentLocation && currentLocation.type === 'requirements'">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="currentLocation.title"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="`${getCheckedBenodigdhedenCount()} van ${currentLocation.requirements?.length || 0} items afgevinkt`"></div>
                                    </div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'end_checklist'">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="currentLocation.title"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="`${getEndChecklistCompletedCount()} van ${getEndChecklistTotalCount() || 0} items afgevinkt`"></div>
                                    </div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'summary'">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Planning overzicht</div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'vehicle_tasks'">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="currentLocation.title || 'Voertuig taken'"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="`${selectedVehicleTasks.length} geselecteerd`"></div>
                                    </div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'location'">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="currentLocation.title"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="`${getCompletedTasksCount(currentLocation)} van ${currentLocation.tasks?.length || 0} taken voltooid`"></div>
                                        <div class="mt-0.5 text-xs text-blue-600 dark:text-blue-300" x-show="hasVehicleTasks(currentLocation)">
                                            <span x-text="`${getCompletedVehicleTasksCount(currentLocation)} van ${getVehicleTasksCount(currentLocation)} voertuig taken eerst`"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'travel'">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="currentLocation.title"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="`${currentLocation.duration_text} naar ${currentLocation.to}`"></div>
                                    </div>
                                </template>
                                <template x-if="currentLocation && currentLocation.type === 'call'">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Bel Jaap</div>
                                </template>
                            </div>
                        <div class="text-right flex items-center space-x-4">
                            <button @click="$dispatch('open-full-overview')" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                                </svg>
                            </button>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="getStepDisplay()"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="getProgressPercentage() + '%'"></div>
                            </div>
                        </div>
                        </div>

                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300 ease-out"
                                :style="`width: ${getProgressPercentage()}%`"></div>
                        </div>
                    </div>
                </div>

                {{-- Full Overview Modal --}}
                <div x-data="{ open: false }"
                     @open-full-overview.window="open = true"
                     x-show="open"
                     class="fixed inset-0 z-50 overflow-y-auto"
                     style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="open"
                             x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
                        </div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div x-show="open"
                             x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                             x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                             class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">

                            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Volledig overzicht</h3>
                                    <button @click="open = false" class="text-gray-400 hover:text-gray-500">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="mt-2 max-h-[70vh] overflow-y-auto">
                                    @include('my-planning.partials.summary-content')
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" @click="open = false" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Sluiten
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Location Steps --}}
                <template x-for="(location, index) in locationSteps" :key="index">
                    <div x-show="currentLocationIndex === index" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6 transition-all duration-300 animate-fade-in">

                        {{-- Benodigdheden (Requirements) Step --}}
                        <template x-if="location.type === 'requirements'">
                            <div>
                                <div class="flex items-center mb-4">
                                    <svg class="w-8 h-8 text-indigo-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="location.details"></p>

                                {{-- Benodigdheden (Requirements) Checklist --}}
                                <div class="space-y-3">
                                    <template x-for="(benodigdheid, benodigdheidIndex) in location.requirements" :key="benodigdheid.id">
                                        <div class="flex items-start space-x-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <input type="checkbox"
                                                    :id="`benodigdheid_${benodigdheid.id}`"
                                                    x-model="benodigdhedenChecked[benodigdheid.id]"
                                                    class="w-5 h-5 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <label :for="`benodigdheid_${benodigdheid.id}`" class="block text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer" x-text="benodigdheid.naam"></label>
                                                <div x-show="benodigdheid.beschrijving" class="mt-1">
                                                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="benodigdheid.beschrijving"></p>
                                                </div>
                                                <template x-if="benodigdheid.locaties && benodigdheid.locaties.length">
                                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 flex-wrap">
                                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 111.314 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        </svg>
                                                        <span class="text-gray-600 dark:text-gray-300">Locaties:</span>
                                                        <span x-text="benodigdheid.locaties.join(', ')"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Progress indicator --}}
                                <div class="mt-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">Voortgang</span>
                                        <span class="text-sm text-indigo-600 dark:text-indigo-400" x-text="`${getCheckedBenodigdhedenCount()} van ${location.requirements.length} afgevinkt`"></span>
                                    </div>
                                    <div class="w-full bg-indigo-200 dark:bg-indigo-800 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full transition-all duration-300"
                                            :style="`width: ${getBenodigdhedenProgress()}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Summary Step --}}
                        <template x-if="location.type === 'summary'">
                            <div>
                                <div class="flex items-center mb-4">
                                    <svg class="w-8 h-8 text-purple-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="location.details"></p>
                                @include('my-planning.partials.summary-content')
                            </div>
                        </template>

                        {{-- Travel Step --}}
                        <template x-if="location.type === 'travel'">
                            <div>
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center">
                                        <svg class="w-8 h-8 text-orange-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 6H3l3.5 7 4.5-7zm4.5 0L21 13l-3.5-7H17.5z"></path>
                                        </svg>
                                        <div>
                                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="`Van ${location.from} naar ${location.to}`"></p>
                                        </div>
                                    </div>

                                    {{-- Travel Timer --}}
                                    @if(Auth::user()->isAdmin())
                                        <div class="text-right">
                                            <div class="text-2xl font-mono font-bold text-blue-900 dark:text-blue-100" x-text="formatDuration(locationElapsedSeconds)"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Tijd op locatie</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Travel Details --}}
                                <div class="mb-6 p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                            Bestemming:
                                            <a :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(location.destination_address || '')}`"
                                                target="_blank" class="text-orange-600 hover:underline" x-text="location.to"></a>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm">
                                        <div class="flex items-center text-orange-700 dark:text-orange-300">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span>Geschatte tijd: <span x-text="location.duration_text"></span></span>
                                        </div>
                                        <div x-show="location.distance_km" class="flex items-center text-orange-700 dark:text-orange-300">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            </svg>
                                            <span>Afstand: <span x-text="location.distance_km + ' km'"></span></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Travel Instructions --}}
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Instructies</h3>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-3">
                                        Start de reis naar je volgende locatie. Gebruik Google Maps voor de beste route.
                                    </p>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(location.destination_address || '')}`"
                                            target="_blank"
                                            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            Open in Google Maps
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Call Step --}}
                        <template x-if="location.type === 'call'">
                            <div>
                                <div class="flex items-center mb-4">
                                    <svg class="w-8 h-8 text-green-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="location.details"></p>

                                {{-- Tasks Overview --}}
                                <div x-show="location.completed_tasks && location.completed_tasks.length > 0" class="mb-6">
                                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                        <h3 class="font-medium text-green-800 dark:text-green-200 mb-3">
                                            <span x-text="`Uitgevoerde taken bij ${location.location_name}:`"></span>
                                        </h3>
                                        <div class="space-y-2">
                                            <template x-for="task in location.completed_tasks" :key="task.task_id">
                                                <div class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 rounded-md border border-green-200 dark:border-green-700">
                                                    <div class="flex-shrink-0 mt-0.5">
                                                        <template x-if="task.status === 'completed'">
                                                            <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'review'">
                                                            <div class="w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'rejected'">
                                                            <div class="w-5 h-5 bg-red-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'skipped'">
                                                            <div class="w-5 h-5 bg-gray-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="task.title"></h4>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="task.details"></p>
                                                        <div class="flex items-center mt-1">
                                                            <span class="text-xs px-2 py-1 rounded-full font-medium"
                                                                :class="{
                                                                      'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': task.status === 'completed',
                                                                      'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100': task.status === 'review',
                                                                      'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': task.status === 'rejected',
                                                                      'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100': task.status === 'skipped'
                                                                  }"
                                                                x-text="task.status === 'completed' ? 'Voltooid' :
                                                                          task.status === 'review' ? 'In review' :
                                                                          task.status === 'rejected' ? 'Afgewezen' :
                                                                          task.status === 'skipped' ? 'Overgeslagen' : task.status">
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- End Checklist Step --}}
                        <template x-if="location.type === 'end_checklist'">
                            <div>
                                <div class="flex items-center mb-4">
                                    <svg class="w-8 h-8 text-orange-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="location.details"></p>


                                {{-- Status Indicator --}}
                                <div class="mb-6 p-4 rounded-lg border"
                                     :class="location.is_approved ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700' :
                                             location.has_submitted ? 'bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-700' :
                                             'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700'">
                                    <template x-if="location.is_approved">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-green-800 dark:text-green-200 font-medium">Eind checklist goedgekeurd - Planning voltooid!</span>
                                        </div>
                                    </template>
                                    <template x-if="!location.is_approved && location.has_submitted">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-orange-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-orange-800 dark:text-orange-200 font-medium">Eind checklist ingediend - Wacht op goedkeuring</span>
                                        </div>
                                    </template>
                                    <template x-if="!location.has_submitted">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-blue-800 dark:text-blue-200 font-medium">Upload foto's voor elk item en dien de checklist in</span>
                                        </div>
                                    </template>
                                </div>

                                {{-- Checklist Items --}}
                                <template x-if="location.checklist_items && location.checklist_items.length > 0">
                                    <div class="space-y-4 mb-6">
                                        <template x-for="(item, itemIndex) in location.checklist_items" :key="item.id">
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                                {{-- Item Header --}}
                                                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex items-start space-x-3">
                                                            <div class="flex-shrink-0 mt-1">
                                                                <template x-if="item.type === 'material'">
                                                                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center">
                                                                        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"></path>
                                                                        </svg>
                                                                    </div>
                                                                </template>
                                                                <template x-if="item.type === 'end_action'">
                                                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                                        </svg>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <div class="min-w-0 flex-1">
                                                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.title"></h4>
                                                                <div x-show="item.description" class="mt-1">
                                                                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="item.description"></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {{-- Status Badge --}}
                                                        <div class="flex-shrink-0 ml-4">
                                                            <template x-if="item.status === 'approved'">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    Goedgekeurd
                                                                </span>
                                                            </template>
                                                            <template x-if="item.status === 'rejected'">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    Afgewezen
                                                                </span>
                                                            </template>
                                                            <template x-if="item.status === 'pending' && (item.photos?.length > 0 || item.photo_path)">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    In behandeling
                                                                </span>
                                                            </template>
                                                            <template x-if="item.status === 'pending' && !(item.photos?.length > 0 || item.photo_path)">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    Foto vereist
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Photo Section --}}
                                                <div class="p-4">
                                                    {{-- Existing Photos --}}
                                                    <template x-if="(item.photos && item.photos.length > 0) || item.photo_url">
                                                        <div class="mb-4">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <div class="flex flex-col">
                                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Geüploade foto(s):</span>
                                                                    <template x-if="item.uploaded_by_name || item.uploaded_at">
                                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                            <span x-show="item.uploaded_by_name" x-text="`Door: ${item.uploaded_by_name}`"></span>
                                                                            <span x-show="item.uploaded_at" x-text="item.uploaded_by_name ? ` op ${new Date(item.uploaded_at).toLocaleString('nl-NL')}` : `Op: ${new Date(item.uploaded_at).toLocaleString('nl-NL')}`"></span>
                                                                            <template x-if="item.location_name">
                                                                                <span x-text="` @ ${item.location_name}`" class="font-medium"></span>
                                                                            </template>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                                <div class="flex items-center gap-3">
                                                                    <template x-if="item.photos && item.photos.length > 0">
                                                                        <a :href="`/plannings/tasks/${item.id}/photos/download`"
                                                                           class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 mr-1"><path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"/><path d="M5 15a1 1 0 011 1v2a1 1 0 001 1h10a1 1 0 001-1v-2a1 1 0 112 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2a1 1 0 011-1z"/></svg>
                                                                            Download alle foto’s
                                                                        </a>
                                                                    </template>
                                                                    <template x-if="item.status === 'pending'">
                                                                        <button @click="removePhoto(item)" class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                                            Alles verwijderen
                                                                        </button>
                                                                    </template>
                                                                </div>
                                                            </div>

                                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                                                <!-- New multi-photos gallery -->
                                                                <template x-if="item.photos && item.photos.length > 0">
                                                                    <template x-for="(p, idx) in item.photos" :key="p.id ?? p.photo_url ?? idx">
                                                                        <div class="relative group">
                                                                            <button type="button" class="block focus:outline-none" @click="openImageModal(item.photos.map(pp => pp.photo_url ?? pp.url ?? pp), idx)">
                                                                                <img :src="p.photo_url ?? p.url ?? p" class="w-full h-36 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer group-hover:opacity-90 transition" :alt="`Foto ${idx+1} voor ${item.title}`">
                                                                            </button>
                                                                            <button x-show="item.status === 'pending'" @click.prevent="removeSinglePhoto(item, p)" class="absolute -top-2 -right-2 z-10 bg-white dark:bg-gray-800 hover:bg-white dark:hover:bg-gray-800 text-red-600 rounded-full p-1 shadow">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 12.062A3 3 0 0115.92 22H8.08a3 3 0 01-2.988-2.278L4.087 6.66l-.209.035a.75.75 0 11-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.972a52.662 52.662 0 013.368 0C15.287 1.805 16.5 3.141 16.5 4.705zM10.5 4.75a.25.25 0 00-.25.25v.136a49.488 49.488 0 013.5 0V5a.25.25 0 00-.25-.25h-3zM8.58 8.72a.75.75 0 10-1.5.06l.626 10.02a1.5 1.5 0 001.494 1.4h7.84a1.5 1.5 0 001.494-1.4l.626-10.02a.75.75 0 10-1.5-.06l-.62 9.93a.25.25 0 01-.249.23H8.7a.25.25 0 01-.25-.23l-.62-9.93z" clip-rule="evenodd"/></svg>
                                                                            </button>
                                                                        </div>
                                                                    </template>
                                                                </template>

                                                                <!-- Legacy single photo fallback -->
                                                                <template x-if="(!item.photos || item.photos.length === 0) && item.photo_url">
                                                                    <button @click="openImageModal([item.photo_url], 0)" class="block">
                                                                        <img :src="item.photo_url" :alt="`Foto voor ${item.title}`" class="w-full h-48 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:opacity-90 transition">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    {{-- Photo Upload (Task-like: DnD + preview queue + upload) --}}
                                                    <!-- Show uploader for all non-approved items so users can add/replace photos even if one already exists -->
                                                    <template x-if="item.status !== 'approved'">
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                                Upload bewijs foto's
                                                            </label>

                                                            <!-- Drag & Drop Zone -->
                                                            <div
                                                                class="border-2 border-dashed rounded-lg p-4 sm:p-6 transition"
                                                                :class="draggingItemId === item.id ? 'border-blue-400 bg-blue-50/50 dark:border-blue-500 dark:bg-blue-900/10' : 'border-gray-300 dark:border-gray-600'"
                                                                @dragover.prevent="draggingItemId = item.id"
                                                                @dragleave.prevent="draggingItemId = null"
                                                                @drop.prevent="handleDrop($event, item)">
                                                                <div class="text-center">
                                                                    <svg class="mx-auto h-10 w-10 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                                    </svg>
                                                                    <div class="mt-3">
                                                                        <label :for="`photo_input_${item.id}`" class="cursor-pointer text-sm font-medium text-blue-700 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                            Bestanden kiezen
                                                                        </label>
                                                                        <span class="mx-1 text-sm text-gray-500 dark:text-gray-400">of sleep ze hierheen</span>
                                                                    </div>
                                                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Meerdere afbeeldingen toegestaan (JPG, PNG, GIF, WEBP, max. 10MB per bestand)</div>
                                                                    <input type="file"
                                                                           class="sr-only"
                                                                           :id="`photo_input_${item.id}`"
                                                                           accept="image/*"
                                                                           multiple
                                                                           @change="queuePhotoFiles($event, item)">
                                                                </div>
                                                            </div>

                                                            <!-- Queued previews -->
                                                            <template x-if="(item._queued && item._queued.length > 0)">
                                                                <div class="mt-4">
                                                                    <div class="flex items-center justify-between mb-2">
                                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="`${item._queued.length} bestand(en) geselecteerd`"></span>
                                                                        <div class="flex items-center gap-2">
                                                                            <button type="button" @click="startUploadQueued(item)" :disabled="uploadingPhoto === item.id" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                                                                                <svg x-show="uploadingPhoto !== item.id" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16V8l-6-4H4zm8 12H8m8-4H8"/></svg>
                                                                                <svg x-show="uploadingPhoto === item.id" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                                                <span x-text="uploadingPhoto === item.id ? 'Uploaden...' : 'Upload geselecteerde'"></span>
                                                                            </button>
                                                                            <button type="button" @click="clearQueuedFiles(item)" :disabled="uploadingPhoto === item.id" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                                                                                Leegmaken
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                                                        <template x-for="(q, qidx) in item._queued" :key="q.previewUrl">
                                                                            <div class="relative group">
                                                                                <button type="button" @click="removeQueuedFile(item, qidx)" class="absolute -top-2 -right-2 z-10 bg-white dark:bg-gray-800 hover:bg-white dark:hover:bg-gray-800 text-red-600 rounded-full p-1 shadow">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 12.062A3 3 0 0115.92 22H8.08a3 3 0 01-2.988-2.278L4.087 6.66l-.209.035a.75.75 0 11-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.972a52.662 52.662 0 013.368 0C15.287 1.805 16.5 3.141 16.5 4.705zM10.5 4.75a.25.25 0 00-.25.25v.136a49.488 49.488 0 013.5 0V5a.25.25 0 00-.25-.25h-3zM8.58 8.72a.75.75 0 10-1.5.06l.626 10.02a1.5 1.5 0 001.494 1.4h7.84a1.5 1.5 0 001.494-1.4l.626-10.02a.75.75 0 10-1.5-.06l-.62 9.93a.25.25 0 01-.249.23H8.7a.25.25 0 01-.25-.23l-.62-9.93z" clip-rule="evenodd"/></svg>
                                                                                </button>
                                                                                <img :src="q.previewUrl" class="w-full h-28 object-cover rounded-lg border border-gray-200 dark:border-gray-600 z-0" :alt="`Geselecteerde foto ${qidx+1}`">
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>

                                                    {{-- Admin Feedback --}}
                                                    <template x-if="item.admin_notes && (item.status === 'rejected' || item.status === 'approved')">
                                                        <div class="mt-4 p-3 rounded-lg"
                                                             :class="item.status === 'rejected' ? 'bg-red-50 dark:bg-red-900/20' : 'bg-green-50 dark:bg-green-900/20'">
                                                            <div class="flex items-start">
                                                                <svg class="w-5 h-5 mt-0.5 mr-2"
                                                                     :class="item.status === 'rejected' ? 'text-red-600' : 'text-green-600'"
                                                                     fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                                </svg>
                                                                <div>
                                                                    <p class="text-sm font-medium"
                                                                       :class="item.status === 'rejected' ? 'text-red-800 dark:text-red-200' : 'text-green-800 dark:text-green-200'">
                                                                        <span x-text="item.status === 'rejected' ? 'Feedback bij afwijzing:' : 'Beoordeeld door:'"></span>
                                                                        <span x-show="item.reviewer_name" x-text="` ${item.reviewer_name}`"></span>
                                                                    </p>
                                                                    <p class="text-sm mt-1"
                                                                       :class="item.status === 'rejected' ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'"
                                                                       x-text="item.admin_notes"></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Submit Button --}}
                                <template x-if="!location.has_submitted && canSubmitEndChecklist()">
                                    <div class="mb-6">
                                        <button @click="submitEndChecklist(location.planning_id)"
                                                :disabled="submittingEndChecklist"
                                                class="w-full bg-orange-600 hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                            <template x-if="submittingEndChecklist">
                                                <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </template>
                                            <span x-text="submittingEndChecklist ? 'Indienen...' : 'Eind checklist indienen voor beoordeling'"></span>
                                        </button>
                                    </div>
                                </template>

                                {{-- Progress indicator --}}
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Voortgang eind checklist</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="`${getEndChecklistCompletedCount()} van ${getEndChecklistTotalCount()}`"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-600">
                                        <div class="bg-orange-600 h-2 rounded-full transition-all duration-300" :style="`width: ${getEndChecklistProgress()}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Vehicle Tasks Step --}}
                        <template x-if="location.type === 'vehicle_tasks'">
                            <div>
                                <div class="flex items-center mb-4">
                                    <svg class="w-8 h-8 text-blue-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h2l1 3h11l1-3h2M5 13l1-3h12l1 3M7 10V7a5 5 0 0110 0v3"></path>
                                    </svg>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Voertuig taken voor de volgende dag</h2>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 mb-6">
                                    Voeg voertuig taken toe voor <span class="font-semibold" x-text="location.vehicle_name || 'het voertuig'"></span>. Deze verschijnen morgen als eerste taken wanneer hetzelfde voertuig is ingepland.
                                </p>

                                <div class="p-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-blue-900 dark:text-blue-100">Voertuig taken</h3>
                                            <p class="text-sm text-blue-800 dark:text-blue-200 mt-1">Maak een selectie uit de standaarden of voeg eigen taken toe.</p>
                                        </div>
                                        <div class="text-xs text-blue-800 dark:text-blue-200" x-show="vehicleDefaultsLoading">Laden...</div>
                                    </div>

                                    {{-- Defaults quick-pick --}}
                                    <div class="mt-4">
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Snelle selectie</div>
                                        <template x-if="vehicleDefaultsError">
                                            <div class="text-sm text-red-700 dark:text-red-300 mb-2" x-text="vehicleDefaultsError"></div>
                                        </template>
                                        <div class="flex flex-wrap gap-2" x-show="vehicleDefaults.length > 0">
                                            <template x-for="def in vehicleDefaults" :key="def.id">
                                                <button type="button" @click="toggleDefaultVehicleTask(def)" class="px-3 py-1 rounded-full text-xs border transition" :class="isDefaultSelected(def.id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-800 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700 hover:bg-blue-50 dark:hover:bg-gray-700'">
                                                    <span x-text="def.title"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="!vehicleDefaultsLoading && vehicleDefaults.length === 0" class="text-sm text-blue-800 dark:text-blue-200">
                                            Geen standaard voertuig taken beschikbaar. Voeg hieronder een eigen taak toe.
                                        </div>
                                    </div>

                                    {{-- Custom creator --}}
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs text-blue-900 dark:text-blue-100 mb-1">Titel (verplicht voor eigen taak)</label>
                                            <input type="text" x-model="customVehicleTask.title" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" placeholder="Bijv. Vuilnis wegbrengen">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-blue-900 dark:text-blue-100 mb-1">Omschrijving</label>
                                            <input type="text" x-model="customVehicleTask.description" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" placeholder="Optioneel">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-blue-900 dark:text-blue-100 mb-1">Geschatte tijd (minuten)</label>
                                            <input type="number" min="0" x-model.number="customVehicleTask.estimated_time_minutes" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" @click="addCustomVehicleTask()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md">Eigen voertuig taak toevoegen</button>
                                    </div>

                                    {{-- Selected list --}}
                                    <div class="mt-4" x-show="selectedVehicleTasks.length > 0">
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Geselecteerde voertuig taken</div>
                                        <ul class="space-y-2">
                                            <template x-for="(vt, idx) in selectedVehicleTasks" :key="vt._key">
                                                <li class="flex items-center justify-between p-3 rounded border bg-white dark:bg-gray-800 border-blue-200 dark:border-blue-700">
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="vt.title || ('Standaard: ' + (vt._default?.title || vt.default_id))"></div>
                                                        <div class="text-xs text-gray-600 dark:text-gray-300" x-show="vt.description" x-text="vt.description"></div>
                                                        <div class="text-xs text-gray-600 dark:text-gray-300" x-show="vt.estimated_time_minutes != null">Tijd: <span x-text="vt.estimated_time_minutes"></span> min</div>
                                                    </div>
                                                    <button type="button" @click="removeSelectedVehicleTask(idx)" class="text-xs px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded">Verwijderen</button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    {{-- Submit vehicle tasks --}}
                                    <div class="mt-4">
                                        <button type="button" @click="submitVehicleTasks(location.planning_id)" :disabled="selectedVehicleTasks.length === 0 || submittingVehicleTasks" class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-md">
                                            <template x-if="submittingVehicleTasks">
                                                <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </template>
                                            <span x-text="submittingVehicleTasks ? 'Toevoegen...' : 'Voertuig taken toevoegen'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Location Step --}}
                        <template x-if="location.type === 'location'">
                            <div>
                                {{-- Location Header --}}
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center">
                                        <svg class="w-8 h-8 text-blue-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <div>
                                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="location.title"></h2>
                                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="location.address"></p>
                                        </div>
                                    </div>

                                    {{-- Location Timer --}}
                                    @if(Auth::user()->isAdmin())
                                        <div class="text-right">
                                            <div class="text-2xl font-mono font-bold text-blue-900 dark:text-blue-100" x-text="formatDuration(locationElapsedSeconds)"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Tijd op locatie</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Tasks List --}}
                                <div class="space-y-4">
                                    {{-- Vehicle tasks hint when present and for the backlog/no-location step --}}
                                    <div x-show="hasVehicleTasks(location) && !location.location_id" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-300 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 20h10a2 2 0 002-2V7a2 2 0 00-2-2h-5l-2-2H7a2 2 0 00-2 2v13a2 2 0 002 2z"></path>
                                            </svg>
                                            <div class="text-sm text-blue-800 dark:text-blue-200">
                                                <p class="font-medium">Voertuig taken eerst</p>
                                                <p class="mt-0.5">Werk eerst de voertuig taken af voordat je aan de overige backlog taken begint.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <template x-for="(task, taskIndex) in location.tasks" :key="task.task_id">
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                            {{-- Task Header --}}
                                            <button @click="toggleTask(index, taskIndex)"
                                                class="w-full p-4 text-left bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="mr-3">
                                                        <template x-if="task.status === 'completed'">
                                                            <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'review'">
                                                            <div class="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'rejected'">
                                                            <div class="w-6 h-6 bg-red-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'skipped'">
                                                            <div class="w-6 h-6 bg-gray-500 rounded-full flex items-center justify-center">
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="task.status === 'open'">
                                                            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                                                        </template>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="font-medium text-gray-900 dark:text-gray-100" x-text="task.title"></h3>
                                                        <span x-show="task.is_vehicle_task" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 border border-blue-200 dark:border-blue-700" title="Voertuig taak" aria-label="Voertuig taak">
                                                            Voertuig
                                                        </span>
                                                        <span x-show="task.is_extra" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 border border-purple-200 dark:border-purple-700" title="Extra toevoeging" aria-label="Extra toevoeging">
                                                            Extra
                                                        </span>
                                                    </div>
                                                </div>
                                                <svg class="w-5 h-5 transform transition-transform"
                                                    :class="isTaskExpanded(index, taskIndex) ? 'rotate-180' : ''"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>

                                            {{-- Task Content --}}
                                            <div x-show="isTaskExpanded(index, taskIndex)" x-transition class="p-4 border-t border-gray-200 dark:border-gray-700">

                                                {{-- Task Details --}}
                                                <div x-show="task.details" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                                    <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">Taak details:</h4>
                                                    <p class="text-sm text-blue-700 dark:text-blue-300 whitespace-pre-wrap" x-text="task.details"></p>
                                                </div>

                                                {{-- Backlog Photos --}}
                                                <div x-show="task.backlog_photos && task.backlog_photos.length > 0" class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Foto's bij taak:</h4>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                                        <template x-for="(photo, photoIndex) in task.backlog_photos" :key="photoIndex">
                                                            <button type="button" class="focus:outline-none group" @click="openImageModal(task.backlog_photos, photoIndex)">
                                                                <img :src="photo" alt="Taak foto" class="w-full h-24 object-cover rounded-lg shadow cursor-pointer hover:opacity-75 transition">
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>

                                                {{-- Completed Status --}}
                                                <div x-show="task.status === 'review' || task.status === 'completed'" class="mb-4 p-3 rounded-lg"
                                                    :class="task.status === 'completed' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-orange-50 dark:bg-orange-900/20'">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <span class="px-3 py-1 text-sm font-semibold rounded-full"
                                                                :class="task.status === 'completed' ? 'bg-green-200 text-green-800' : 'bg-orange-200 text-orange-800'"
                                                                x-text="`Status: ${task.status}`">
                                                            </span>
                                                            <p class="text-sm mt-2"
                                                                :class="task.status === 'completed' ? 'text-green-700 dark:text-green-300' : 'text-orange-700 dark:text-orange-300'"
                                                                x-text="task.status === 'completed' ? 'Deze taak is voltooid en goedgekeurd.' : 'Deze taak is ingediend en wacht op goedkeuring.'">
                                                            </p>
                                                        </div>
                                                        <div x-show="task.status === 'review'">
                                                            <button @click="reopenTask(task)" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 transition">
                                                                Heropen
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Display submitted data --}}
                                                    <div class="mt-4" x-show="task.completed_notes || (task.photos && task.photos.length > 0)">
                                                        <h5 class="font-bold text-gray-800 dark:text-gray-200">Ingestuurde gegevens:</h5>
                                                        <div x-show="task.completed_notes" class="mt-2">
                                                            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap" x-text="task.completed_notes"></p>
                                                        </div>
                                                        <div x-show="task.photos && task.photos.length > 0" class="mt-2">
                                                            <div class="flex flex-wrap gap-2">
                                                                <template x-for="photo in task.photos" :key="photo">
                                                                    <button @click="openImageModal([photo], 0)">
                                                                        <img :src="photo" class="w-24 h-24 object-cover rounded shadow-md hover:shadow-lg transition-shadow cursor-pointer">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Rejected Status --}}
                                                <div x-show="task.status === 'rejected'" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-200 text-red-800">
                                                                Status: Afgewezen
                                                            </span>
                                                            <p class="text-sm mt-2 text-red-700 dark:text-red-300">
                                                                Deze taak is afgewezen en moet opnieuw worden uitgevoerd.
                                                            </p>
                                                            <div x-show="task.rejection_reason" class="mt-2">
                                                                <p class="text-sm font-medium text-red-800 dark:text-red-200">Reden voor afwijzing:</p>
                                                                <p class="text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap" x-text="task.rejection_reason"></p>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <button @click="reopenTask(task)" class="inline-flex items-center px-4 py-2 bg-red-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600 transition">
                                                                Heropen
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Display previously submitted data --}}
                                                    <div class="mt-4" x-show="task.completed_notes || (task.photos && task.photos.length > 0)">
                                                        <h5 class="font-bold text-red-800 dark:text-red-200">Eerder ingestuurde gegevens:</h5>
                                                        <div x-show="task.completed_notes" class="mt-2">
                                                            <p class="text-red-700 dark:text-red-300 whitespace-pre-wrap" x-text="task.completed_notes"></p>
                                                        </div>
                                                        <div x-show="task.photos && task.photos.length > 0" class="mt-2">
                                                            <div class="flex flex-wrap gap-2">
                                                                <template x-for="photo in task.photos" :key="photo">
                                                                    <button @click="openImageModal([photo], 0)">
                                                                        <img :src="photo" class="w-24 h-24 object-cover rounded shadow-md hover:shadow-lg transition-shadow cursor-pointer opacity-75">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Skipped Status --}}
                                                <div x-show="task.status === 'skipped'" class="mb-4 p-3 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                                Status: Overgeslagen
                                                            </span>
                                                            <p class="text-sm mt-2 text-gray-700 dark:text-gray-300">
                                                                Deze taak is overgeslagen na overleg.
                                                            </p>
                                                            <div x-show="task.skip_reason" class="mt-2">
                                                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Reden voor overslaan:</p>
                                                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap" x-text="task.skip_reason"></p>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <button @click="reopenTask(task)" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 transition">
                                                                Heropen
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Display skip data if available --}}
                                                    <div class="mt-4" x-show="task.skip_photos && task.skip_photos.length > 0">
                                                        <h5 class="font-bold text-gray-800 dark:text-gray-200">Foto's bij overslaan:</h5>
                                                        <div class="mt-2">
                                                            <div class="flex flex-wrap gap-2">
                                                                <template x-for="photo in task.skip_photos" :key="photo">
                                                                    <button @click="openImageModal([photo], 0)">
                                                                        <img :src="photo" class="w-24 h-24 object-cover rounded shadow-md hover:shadow-lg transition-shadow cursor-pointer opacity-75">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Task Form --}}
                                                <div x-show="task.status === 'open'" class="space-y-4">

                                                    <div>
                                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Opmerkingen</label>
                                                        <textarea x-model="getTaskCompletion(task.task_id).notes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-900 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
                                                        <div class="mt-2 text-sm text-red-600" x-show="getTaskErrors(task.task_id).notes" x-text="getTaskErrors(task.task_id).notes"></div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                                            Foto's
                                                            <template x-if="task.is_photo_required">
                                                                <span class="text-red-500 font-normal ml-1">(verplicht)</span>
                                                            </template>
                                                        </label>

                                                        <!-- Drag & Drop Zone for Task Photos -->
                                                        <div
                                                            class="border-2 border-dashed rounded-lg p-4 sm:p-5 transition"
                                                            :class="{
                                                                'border-blue-400 bg-blue-50/50 dark:border-blue-500 dark:bg-blue-900/10': draggingTaskId === task.task_id,
                                                                'border-red-300 bg-red-50/30 dark:border-red-900/20': getTaskErrors(task.task_id).photos,
                                                                'border-gray-300 dark:border-gray-600': draggingTaskId !== task.task_id && !getTaskErrors(task.task_id).photos
                                                            }"
                                                            @dragover.prevent="draggingTaskId = task.task_id"
                                                            @dragleave.prevent="draggingTaskId = null"
                                                            @drop.prevent="onTaskDrop($event, task.task_id)"
                                                            @paste="onTaskPaste($event, task.task_id)"
                                                        >
                                                            <div class="text-center">
                                                                <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                                </svg>
                                                                <div class="mt-2">
                                                                    <label :for="`task_photos_${task.task_id}`" class="cursor-pointer text-sm font-medium text-blue-700 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                        Bestanden kiezen
                                                                    </label>
                                                                    <span class="mx-1 text-sm text-gray-500 dark:text-gray-400">of sleep ze hierheen</span>
                                                                </div>
                                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Meerdere afbeeldingen toegestaan (JPG, PNG, GIF, WEBP ≤ 10MB per bestand)</div>
                                                                <input :id="`task_photos_${task.task_id}`" class="sr-only" type="file" accept="image/*" multiple @change="queueTaskPhotoFiles($event, task.task_id)">
                                                            </div>
                                                        </div>

                                                        <!-- Previous photos for this task -->
                                                        <div class="mt-3" x-show="task.photos && task.photos.length > 0">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Eerder toegevoegde foto's:</span>
                                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-2">
                                                                <template x-for="photo in task.photos" :key="photo">
                                                                    <div class="relative">
                                                                        <img :src="photo" class="w-full h-28 object-cover rounded-lg border border-gray-200 dark:border-gray-600 opacity-75">
                                                                        <div class="absolute inset-0 flex items-center justify-center">
                                                                            <span class="bg-gray-900/50 text-white text-[10px] px-1.5 py-0.5 rounded">Bewaard</span>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <p class="text-[10px] text-gray-500 mt-1">* Eerdere foto's blijven behouden tenzij je nieuwe toevoegt.</p>
                                                        </div>

                                                        <!-- Queued previews for this task -->
                                                        <div class="mt-3" x-show="getTaskCompletion(task.task_id).photos.length > 0">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="`${getTaskCompletion(task.task_id).photos.length} bestand(en) geselecteerd`"></span>
                                                                <div class="flex items-center gap-2">
                                                                    <button type="button" @click="clearTaskQueuedFiles(task.task_id)" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                                                        Leegmaken
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                                                <template x-for="(file, idx) in getTaskCompletion(task.task_id).photos" :key="file.name + idx">
                                                                    <div class="relative group">
                                                                        <img :src="URL.createObjectURL(file)" class="w-full h-28 object-cover rounded-lg border border-gray-200 dark:border-gray-600" :alt="`Geselecteerde foto ${idx+1}`">
                                                                        <button type="button" @click="removeTaskQueuedFile(task.task_id, idx)" class="absolute -top-2 -right-2 z-10 bg-white dark:bg-gray-800 hover:bg-white dark:hover:bg-gray-800 text-red-600 rounded-full p-1 shadow">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 12.062A3 3 0 0115.92 22H8.08a3 3 0 01-2.988-2.278L4.087 6.66l-.209.035a.75.75 0 11-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.972a52.662 52.662 0 013.368 0C15.287 1.805 16.5 3.141 16.5 4.705zM10.5 4.75a.25.25 0 00-.25.25v.136a49.488 49.488 0 013.5 0V5a.25.25 0 00-.25-.25h-3zM8.58 8.72a.75.75 0 10-1.5.06l.626 10.02a1.5 1.5 0 001.494 1.4h7.84a1.5 1.5 0 001.494-1.4l.626-10.02a.75.75 0 10-1.5-.06l-.62 9.93a.25.25 0 01-.249.23H8.7a.25.25 0 01-.25-.23l-.62-9.93z" clip-rule="evenodd"/></svg>
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>

                                                        <div class="mt-2 text-sm text-red-600" x-show="getTaskErrors(task.task_id).photos" x-text="getTaskErrors(task.task_id).photos"></div>
                                                    </div>

                                                    <div class="flex justify-between items-end">
                                                        <button @click="openSkipModal(task)" type="button" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:underline">
                                                            Taak overslaan...
                                                        </button>
                                                        <div class="text-right">
                                                            <button @click="submitTaskCompletion(task)" :disabled="isSubmitting" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                                                                <span x-show="!isSubmitting">Voltooien</span>
                                                                <span x-show="isSubmitting">Bezig...</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Comments List --}}
                                    <div class="mt-8 space-y-4" x-show="location.comments && location.comments.length > 0">
                                        <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300">Opmerkingen</h4>
                                        <template x-for="comment in location.comments" :key="'comment-' + comment.id">
                                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div x-show="editingCommentId !== comment.id">
                                                            <p class="text-sm text-blue-800 dark:text-blue-200 whitespace-pre-wrap" x-text="comment.comment"></p>
                                                            <div x-show="comment.photos && comment.photos.length > 0" class="mt-3 flex flex-wrap gap-2">
                                                                <template x-for="(photo, pIdx) in comment.photos" :key="pIdx">
                                                                    <button @click="openImageModal(comment.photos, pIdx)">
                                                                        <img :src="photo" class="w-20 h-20 object-cover rounded shadow-sm hover:opacity-75 transition">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>

                                                        {{-- Edit Comment Form --}}
                                                        <div x-show="editingCommentId === comment.id" class="space-y-3">
                                                            <textarea
                                                                x-model="editCommentNotes"
                                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                                                                rows="3"
                                                            ></textarea>

                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Extra foto's toevoegen</label>
                                                                <div class="flex items-center gap-2">
                                                                    <label class="cursor-pointer inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
                                                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                        </svg>
                                                                        Kies bestanden
                                                                        <input type="file" multiple accept="image/*" class="hidden" @change="queueEditCommentPhotoFiles">
                                                                    </label>
                                                                    <span class="text-xs text-gray-500" x-text="`${editCommentPhotos.length} geselecteerd`"></span>
                                                                </div>

                                                                <div x-show="editCommentPhotos.length > 0" class="mt-2 flex flex-wrap gap-2">
                                                                    <template x-for="(file, fIndex) in editCommentPhotos" :key="'edit-photo-' + fIndex">
                                                                        <div class="relative group">
                                                                            <img :src="URL.createObjectURL(file)" class="w-12 h-12 object-cover rounded shadow-sm">
                                                                            <button @click="removeEditCommentQueuedFile(fIndex)" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0.5 shadow-sm hover:bg-red-600 transition">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </div>

                                                            <div class="flex justify-end gap-2">
                                                                <button @click="cancelEditingComment()" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                                                    Annuleren
                                                                </button>
                                                                <button @click="updateComment(comment)" :disabled="isUpdatingComment" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 disabled:opacity-50">
                                                                    <svg x-show="isUpdatingComment" class="animate-spin -ml-1 mr-2 h-3 w-3 text-white" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                    Opslaan
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Edit Button (only for owner or admin) --}}
                                                    <div class="ml-4" x-show="editingCommentId !== comment.id && (comment.user_id == {{ Auth::id() }} || {{ Auth::user()->isAdmin() ? 'true' : 'false' }})">
                                                        <button @click="startEditingComment(comment)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Extra Section --}}
                                    <div class="mt-8 border-t border-gray-100 dark:border-gray-700 pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Extra opmerking toevoegen</h4>
                                            <button @click="showExtraForm = !showExtraForm"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-500 text-white hover:bg-blue-600 transition shadow-lg">
                                                <svg class="w-6 h-6 transition-transform" :class="showExtraForm ? 'rotate-45' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                            </button>
                                        </div>

                                        {{-- Extra Task Form --}}
                                        <div x-show="showExtraForm" x-transition class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-blue-100 dark:border-blue-900 mb-6" @paste="onExtraPaste($event)">
                                            <div class="space-y-4">
                                                <div x-show="false">
                                                    <x-form-input
                                                        name="extraTaskTitle"
                                                        label="Titel / Onderwerp"
                                                        placeholder="Bijv. Extra lamp vervangen of Opmerking over deur"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <x-form-textarea
                                                        x-model="extraTaskNotes"
                                                        name="description"
                                                        label="Opmerking"
                                                        placeholder="Typ hier je extra opmerking..."
                                                        rows="3"
                                                    />
                                                    <template x-if="extraTaskErrors.notes">
                                                        <p class="mt-1 text-sm text-red-600" x-text="extraTaskErrors.notes[0]"></p>
                                                    </template>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Foto's toevoegen</label>
                                                    <div class="flex items-center gap-4">
                                                        <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
                                                            <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            </svg>
                                                            Kies bestanden
                                                            <input type="file" multiple accept="image/*" class="hidden" @change="queueExtraPhotoFiles">
                                                        </label>
                                                        <span class="text-xs text-gray-500" x-text="`${extraTaskPhotos.length} geselecteerd`"></span>
                                                    </div>

                                                    <div x-show="extraTaskPhotos.length > 0" class="mt-4 flex flex-wrap gap-2">
                                                        <template x-for="(file, fIndex) in extraTaskPhotos" :key="fIndex">
                                                            <div class="relative group">
                                                                <img :src="URL.createObjectURL(file)" class="w-20 h-20 object-cover rounded-lg border dark:border-gray-600">
                                                                <button @click="removeExtraQueuedFile(fIndex)"
                                                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow hover:bg-red-600">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                                <div class="flex justify-end gap-3 pt-2">
                                                    <button @click="showExtraForm = false"
                                                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 transition">
                                                        Annuleren
                                                    </button>
                                                    <button @click="submitExtraTask"
                                                        :disabled="isSubmittingExtra"
                                                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-blue-700 transition disabled:opacity-50">
                                                        <svg x-show="isSubmittingExtra" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        Toevoegen
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Completion Animation --}}
                <div x-show="isCompleted()" class="text-center animate-fade-in bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <template x-if="isEndChecklistWaitingApproval()">
                        <div>
                            <h2 class="text-2xl font-bold text-blue-500 mb-4">Planning Ingediend!</h2>
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-blue-100 dark:bg-blue-900/50 rounded-full">
                                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                    Alle taken en foto's zijn ingediend
                                </h3>
                                <p class="text-blue-700 dark:text-blue-300 mb-4">
                                    Je planning wacht nu op goedkeuring van de beheerder. Je ontvangt bericht zodra de planning is beoordeeld.
                                </p>
                                <div class="inline-flex items-center px-4 py-2 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Wacht op beoordeling
                                </div>
                            </div>
                        </div>
                    </template>
                    <template x-if="!isEndChecklistWaitingApproval()">
                        <div>
                            <h2 class="text-2xl font-bold text-green-500 mb-4">Planning Voltooid!</h2>
                            <div id="lottie-animation" style="width: 300px; height: 300px; margin: auto;"></div>
                        </div>
                    </template>
                </div>

                {{-- Navigation --}}
                <div class="flex justify-between mt-8" x-show="!isCompleted()">
                    <button @click="goToPreviousLocation()"
                        :disabled="currentLocationIndex === 0"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                        Vorige
                    </button>
                    <button @click="goToNextLocation()"
                        :disabled="!canProceedToNext()"
                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-text="getNextButtonText()"></span>
                    </button>
                </div>

                {{-- Skip Task Modal --}}
                <div x-show="isSkipModalOpen"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
                    @keydown.escape.window="isSkipModalOpen = false"
                    style="display: none;">
                    <div @click.away="isSkipModalOpen = false" class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-lg">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Taak overslaan</h3>
                        <p class="text-sm text-yellow-700 dark:text-yellow-500 bg-yellow-100 dark:bg-yellow-900/50 p-3 rounded-md mb-4">
                            Let op: Het overslaan van een taak kan alleen na overleg.
                        </p>
                        <form @submit.prevent="submitSkipTask()" class="space-y-4">
                            <div>
                                <label for="skip_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reden (verplicht)</label>
                                <textarea x-model="skipCompletion.reason" id="skip_reason" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-600" rows="3"></textarea>
                                <div class="mt-1 text-sm text-red-600" x-show="skipErrors.reason" x-text="skipErrors.reason"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto's (optioneel)</label>
                                <input type="file" multiple @change="handleSkipFileUpload($event)" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                <div id="skip-photo-previews" class="mt-2 flex flex-wrap gap-2"></div>
                                <div class="mt-1 text-sm text-red-600" x-show="skipErrors.photos" x-text="skipErrors.photos"></div>
                            </div>
                            <div class="mt-6 flex justify-end space-x-4">
                                <button type="button" @click="isSkipModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                    Annuleren
                                </button>
                                <button type="submit" :disabled="isSubmitting" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                                    <span x-show="!isSubmitting">Overslaan bevestigen</span>
                                    <span x-show="isSubmitting">Bezig...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('locationPlanning', () => ({
                currentLocationIndex: 0,
                locationSteps: [],
                planningId: null,
                isSubmitting: false,
                isSubmittingExtra: false,
                showExtraForm: false,
                extraTaskTitle: '',
                extraTaskNotes: '',
                extraTaskPhotos: [],
                extraTaskErrors: {},
                isSkipModalOpen: false,
                skipCompletion: {
                    reason: '',
                    photos: []
                },
                skipErrors: {},
                skipTaskId: null,
                expandedTasks: {},
                taskCompletions: {},
                taskErrors: {},
                locationStartTime: null,
                locationTimer: null,
                locationElapsedSeconds: 0,
                locationBaseDuration: 0, // For restarted timers
                benodigdhedenChecked: {},
                endChecklistChecked: {},
                uploadingPhoto: null, // Track which photo is being uploaded
                submittingEndChecklist: false, // Track checklist submission state
                draggingTaskId: null, // For DnD state on task photo uploader

                // Vehicle tasks state
                vehicleDefaults: [],
                vehicleDefaultsLoading: false,
                vehicleDefaultsError: null,
                selectedVehicleTasks: [], // items: { default_id } or { title, description, estimated_time_minutes }
                customVehicleTask: { title: '', description: '', estimated_time_minutes: null },
                submittingVehicleTasks: false,

                // Comment editing state
                editingCommentId: null,
                editCommentNotes: '',
                editCommentPhotos: [],
                isUpdatingComment: false,

                async init() {
                    this.locationSteps = JSON.parse(this.$root.dataset.locationSteps);
                    this.planningId = this.$root.dataset.planningId;

                    // Initialize task completions
                    this.locationSteps.forEach((location, locationIndex) => {
                        if (location.type === 'location' && location.tasks) {
                            location.tasks.forEach(task => {
                                this.taskCompletions[task.task_id] = {
                                    notes: task.completed_notes || '',
                                    photos: []
                                };
                                this.taskErrors[task.task_id] = {};
                            });
                        }
                    });

                    const savedLocationIndex = localStorage.getItem(`planning_location_${this.planningId}`);
                    if (savedLocationIndex && !isNaN(savedLocationIndex) && parseInt(savedLocationIndex, 10) < this.locationSteps.length) {
                        this.currentLocationIndex = parseInt(savedLocationIndex, 10);
                    }

                    await this.startLocationTimer();

                    // Initialize requirements checklist state
                    this.initializeBenodigdhedenChecked();
                    this.initializeEndChecklistChecked();

                    this.$watch('currentLocationIndex', async (value) => {
                        localStorage.setItem(`planning_location_${this.planningId}`, value);
                        await this.startLocationTimer();
                        if (this.isCompleted()) this.playAnimation();
                        // Lazy-load vehicle task defaults when entering the vehicle tasks step
                        const cur = this.currentLocation;
                        if (cur && cur.type === 'vehicle_tasks') {
                            this.loadVehicleDefaultsIfNeeded();
                        }
                    });

                    window.addEventListener('beforeunload', () => {
                        this.stopLocationTimer(false);
                    });

                    // Also try to load defaults if we start directly on vehicle tasks
                    const curAtStart = this.currentLocation;
                    if (curAtStart && curAtStart.type === 'vehicle_tasks') {
                        this.loadVehicleDefaultsIfNeeded();
                    }
                },

                get currentLocation() {
                    return this.locationSteps[this.currentLocationIndex] || null;
                },

                shouldShowCollapsedSummary() {
                    // Find the index of the summary step
                    const summaryIndex = this.locationSteps.findIndex(step => step.type === 'summary');
                    // Show collapsed summary only after we've passed the summary step
                    return summaryIndex >= 0 && this.currentLocationIndex > summaryIndex &&
                        this.currentLocation && this.currentLocation.type !== 'summary';
                },

                toggleTask(locationIndex, taskIndex) {
                    const key = `${locationIndex}-${taskIndex}`;
                    this.expandedTasks[key] = !this.expandedTasks[key];
                },

                isTaskExpanded(locationIndex, taskIndex) {
                    const key = `${locationIndex}-${taskIndex}`;
                    return !!this.expandedTasks[key];
                },

                getTaskCompletion(taskId) {
                    return this.taskCompletions[taskId] || {
                        notes: '',
                        photos: []
                    };
                },

                getTaskErrors(taskId) {
                    return this.taskErrors[taskId] || {};
                },

                // Task DnD uploader helpers
                queueTaskPhotoFiles(event, taskId) {
                    const files = Array.from(event.target?.files || event.files || []);
                    if (!files.length) return;
                    const completion = this.getTaskCompletion(taskId);
                    if (!completion.photos) completion.photos = [];

                    for (const file of files) {
                        // Validate size ≤ 10MB
                        if (file.size > 10 * 1024 * 1024) {
                            alert('Bestand is te groot. Maximum 10MB per bestand.');
                            continue;
                        }
                        // Validate type image/*
                        if (!file.type || !file.type.startsWith('image/')) {
                            alert('Alleen afbeeldingen zijn toegestaan.');
                            continue;
                        }
                        completion.photos.push(file);
                    }

                    // Reset input so selecting same files again works
                    if (event.target) {
                        event.target.value = '';
                    }
                },

                onTaskDrop(e, taskId) {
                    const dt = e.dataTransfer;
                    if (!dt || !dt.files) return;
                    this.queueTaskPhotoFiles({ target: { files: dt.files } }, taskId);
                    this.draggingTaskId = null;
                },

                onTaskPaste(event, taskId) {
                    const items = (event.clipboardData || event.originalEvent?.clipboardData)?.items;
                    if (!items) return;

                    const files = [];
                    for (const item of items) {
                        if (item.type.indexOf('image') !== -1) {
                            const blob = item.getAsFile();
                            if (blob) files.push(blob);
                        }
                    }
                    if (files.length > 0) {
                        this.queueTaskPhotoFiles({ files: files }, taskId);
                    }
                },

                onExtraPaste(event) {
                    const items = (event.clipboardData || event.originalEvent?.clipboardData)?.items;
                    if (!items) return;

                    const files = [];
                    for (const item of items) {
                        if (item.type.indexOf('image') !== -1) {
                            const blob = item.getAsFile();
                            if (blob) files.push(blob);
                        }
                    }
                    if (files.length > 0) {
                        this.queueExtraPhotoFiles({ files: files });
                    }
                },

                clearTaskQueuedFiles(taskId) {
                    const completion = this.getTaskCompletion(taskId);
                    completion.photos = [];
                },

                removeTaskQueuedFile(taskId, index) {
                    const completion = this.getTaskCompletion(taskId);
                    if (!Array.isArray(completion.photos)) return;
                    completion.photos.splice(index, 1);
                },

                openImageModal(imageUrls, startIndex) {
                    this.$dispatch('open-image-modal', {
                        imageUrls,
                        startIndex
                    });
                },

                queueExtraPhotoFiles(event) {
                    const files = Array.from(event.target?.files || event.files || []);
                    if (!files.length) return;

                    for (const file of files) {
                        if (file.size > 10 * 1024 * 1024) {
                            alert('Bestand is te groot. Maximum 10MB per bestand.');
                            continue;
                        }
                        if (!file.type || !file.type.startsWith('image/')) {
                            alert('Alleen afbeeldingen zijn toegestaan.');
                            continue;
                        }
                        this.extraTaskPhotos.push(file);
                    }

                    if (event.target) {
                        event.target.value = '';
                    }
                },

                removeExtraQueuedFile(index) {
                    this.extraTaskPhotos.splice(index, 1);
                },

                submitExtraTask() {
                    if (!this.extraTaskNotes.trim()) {
                        this.extraTaskErrors = { notes: ['Opmerking is verplicht'] };
                        return;
                    }

                    this.isSubmittingExtra = true;
                    this.extraTaskErrors = {};

                    const locationId = this.currentLocation.location_id || 'backlog';
                    const url = `/plannings/${this.planningId}/locations/${locationId}/extra-task`;

                    const formData = new FormData();
                    formData.append('title', this.extraTaskTitle || 'Opmerking');
                    formData.append('notes', this.extraTaskNotes);
                    this.extraTaskPhotos.forEach(photo => {
                        formData.append('photos[]', photo);
                    });

                    axios.post(url, formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        })
                        .then(response => {
                            const newComment = response.data.comment;

                            // Add to current location's comments
                            if (!this.currentLocation.comments) {
                                this.currentLocation.comments = [];
                            }
                            this.currentLocation.comments.push(newComment);

                            // Reset form
                            this.extraTaskTitle = '';
                            this.extraTaskNotes = '';
                            this.extraTaskPhotos = [];
                            this.showExtraForm = false;
                            this.isSubmittingExtra = false;

                            if (this.checkAndUpdateRouteStatus) {
                                this.checkAndUpdateRouteStatus();
                            }
                        })
                        .catch(error => {
                            this.isSubmittingExtra = false;
                            if (error.response && error.response.status === 422) {
                                this.extraTaskErrors = error.response.data.errors;
                            } else {
                                alert('Er is een fout opgetreden bij het toevoegen van de extra taak.');
                            }
                        });
                },

                startEditingComment(comment) {
                    this.editingCommentId = comment.id;
                    this.editCommentNotes = comment.comment;
                    this.editCommentPhotos = [];
                },

                cancelEditingComment() {
                    this.editingCommentId = null;
                    this.editCommentNotes = '';
                    this.editCommentPhotos = [];
                },

                queueEditCommentPhotoFiles(event) {
                    const files = Array.from(event.target?.files || event.files || []);
                    if (!files.length) return;

                    for (const file of files) {
                        if (file.size > 10 * 1024 * 1024) {
                            alert('Bestand is te groot. Maximum 10MB per bestand.');
                            continue;
                        }
                        if (!file.type || !file.type.startsWith('image/')) {
                            alert('Alleen afbeeldingen zijn toegestaan.');
                            continue;
                        }
                        this.editCommentPhotos.push(file);
                    }

                    if (event.target) {
                        event.target.value = '';
                    }
                },

                removeEditCommentQueuedFile(index) {
                    this.editCommentPhotos.splice(index, 1);
                },

                updateComment(comment) {
                    if (!this.editCommentNotes.trim()) {
                        alert('Opmerking is verplicht');
                        return;
                    }

                    this.isUpdatingComment = true;

                    const formData = new FormData();
                    formData.append('_method', 'PUT');
                    formData.append('notes', this.editCommentNotes);
                    this.editCommentPhotos.forEach(photo => {
                        formData.append('photos[]', photo);
                    });

                    axios.post(`/planning-comments/${comment.id}`, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })
                    .then(response => {
                        comment.comment = response.data.comment.comment;
                        comment.photos = response.data.comment.photos;
                        this.editingCommentId = null;
                        this.editCommentNotes = '';
                        this.editCommentPhotos = [];
                        this.isUpdatingComment = false;
                    })
                    .catch(error => {
                        this.isUpdatingComment = false;
                        alert('Er is een fout opgetreden bij het bijwerken van de opmerking.');
                    });
                },

                submitTaskCompletion(task) {
                    this.isSubmitting = true;
                    this.taskErrors[task.task_id] = {};

                    const formData = new FormData();
                    const completion = this.taskCompletions[task.task_id];
                    // Client-side validatie: foto verplicht indien taak dit vereist
                    if (task.is_photo_required && (!completion.photos || completion.photos.length === 0)) {
                        this.taskErrors[task.task_id] = { photos: 'Foto is verplicht voor deze taak.' };
                        this.isSubmitting = false;
                        return;
                    }

                    formData.append('completed_notes', completion.notes);
                    formData.append('task_duration_seconds', this.getLocationDuration());
                    completion.photos.forEach(photo => {
                        formData.append('photos[]', photo);
                    });

                    const url = `/plannings/${this.planningId}/tasks/${task.task_id}/submit-completion`;

                    axios.post(url, formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        })
                        .then(response => {
                            const updatedTask = response.data.task;
                            task.status = updatedTask.status;
                            task.completed_notes = completion.notes;
                            task.photos = response.data.task.photos || [];

                            // Update corresponding tasks in call steps
                            this.updateTaskInCallSteps(task.task_id, {
                                status: updatedTask.status,
                                completed_notes: completion.notes,
                                photos: response.data.task.photos || []
                            });

                            // Clear form
                            this.taskCompletions[task.task_id] = {
                                notes: '',
                                photos: []
                            };
                            this.updateTaskPhotoPreviews(task.task_id);
                        })
                        .catch(error => {
                            if (error.response && error.response.status === 422) {
                                const validationErrors = error.response.data.errors;
                                this.taskErrors[task.task_id] = {};
                                if (validationErrors.completed_notes) this.taskErrors[task.task_id].notes = validationErrors.completed_notes[0];
                                if (validationErrors.photos) this.taskErrors[task.task_id].photos = validationErrors.photos[0];
                            } else {
                                console.error('Er was een fout bij het bijwerken van de taak:', error);
                            }
                        })
                        .finally(() => {
                            this.isSubmitting = false;
                        });
                },

                openSkipModal(task) {
                    this.skipTaskId = task.task_id;
                    this.isSkipModalOpen = true;
                },

                handleSkipFileUpload(event) {
                    this.skipCompletion.photos = Array.from(event.target.files);
                    this.updateSkipPhotoPreviews();
                },

                updateSkipPhotoPreviews() {
                    const container = document.getElementById('skip-photo-previews');
                    if (!container) return;

                    container.innerHTML = '';
                    this.skipCompletion.photos.forEach(file => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'w-16 h-16 object-cover rounded';
                            container.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    });
                },

                submitSkipTask() {
                    this.isSubmitting = true;
                    this.skipErrors = {};

                    const formData = new FormData();
                    formData.append('reason', this.skipCompletion.reason);
                    formData.append('task_duration_seconds', this.getLocationDuration());
                    this.skipCompletion.photos.forEach(photo => {
                        formData.append('photos[]', photo);
                    });

                    const url = `/plannings/${this.planningId}/tasks/${this.skipTaskId}/skip`;

                    axios.post(url, formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        })
                        .then(response => {
                            // Find and update the task
                            this.locationSteps.forEach(location => {
                                if (location.tasks) {
                                    const task = location.tasks.find(t => t.task_id === this.skipTaskId);
                                    if (task) {
                                        task.status = 'skipped';
                                        task.skip_reason = this.skipCompletion.reason;
                                        task.skip_photos = response.data.skip_photos || [];
                                    }
                                }
                            });

                            // Update corresponding tasks in call steps
                            this.updateTaskInCallSteps(this.skipTaskId, {
                                status: 'skipped',
                                skip_reason: this.skipCompletion.reason,
                                skip_photos: response.data.skip_photos || []
                            });

                            this.isSkipModalOpen = false;
                            this.skipCompletion = {
                                reason: '',
                                photos: []
                            };
                            this.skipTaskId = null;
                        })
                        .catch(error => {
                            if (error.response && error.response.status === 422) {
                                const validationErrors = error.response.data.errors;
                                this.skipErrors = {};
                                if (validationErrors.reason) this.skipErrors.reason = validationErrors.reason[0];
                                if (validationErrors.photos) this.skipErrors.photos = validationErrors.photos[0];
                            } else {
                                console.error('Er was een fout bij het overslaan van de taak:', error);
                            }
                        })
                        .finally(() => {
                            this.isSubmitting = false;
                        });
                },

                reopenTask(task) {
                    const url = `/plannings/${this.planningId}/tasks/${task.task_id}/reopen`;
                    axios.post(url)
                        .then(response => {
                            task.status = response.data.task.status;
                            task.photos = response.data.task.photos || [];

                            // Update local state so changes are visible immediately without refresh
                            if (this.taskCompletions[task.task_id]) {
                                this.taskCompletions[task.task_id].notes = task.completed_notes || '';
                            }

                            // Update corresponding tasks in call steps
                            this.updateTaskInCallSteps(task.task_id, {
                                status: response.data.task.status,
                                photos: task.photos
                            });
                        })
                        .catch(error => {
                            console.error('Kon de taak niet heropenen:', error);
                            alert(error.response.data.message || 'Er is een fout opgetreden.');
                        });
                },

                getCompletedTasksCount(location) {
                    if (!location.tasks) return 0;
                    return location.tasks.filter(task => task.status === 'completed' || task.status === 'review').length;
                },

                // Vehicle task helpers for UI badges/counters
                hasVehicleTasks(location) {
                    return Array.isArray(location?.tasks) && location.tasks.some(t => !!t.is_vehicle_task);
                },

                getVehicleTasksCount(location) {
                    if (!Array.isArray(location?.tasks)) return 0;
                    return location.tasks.filter(t => !!t.is_vehicle_task).length;
                },

                getCompletedVehicleTasksCount(location) {
                    if (!Array.isArray(location?.tasks)) return 0;
                    return location.tasks.filter(t => !!t.is_vehicle_task && (t.status === 'completed' || t.status === 'review')).length;
                },

                updateTaskInCallSteps(taskId, updates) {
                    // Find and update the task in all call steps
                    this.locationSteps.forEach(step => {
                        if (step.type === 'call' && step.completed_tasks) {
                            const taskToUpdate = step.completed_tasks.find(t => t.task_id === taskId);
                            if (taskToUpdate) {
                                Object.assign(taskToUpdate, updates);
                            }
                        }
                    });
                },

                // ---------- Vehicle tasks helpers ----------
                loadVehicleDefaultsIfNeeded() {
                    if (this.vehicleDefaultsLoading || this.vehicleDefaults.length > 0) return;
                    this.vehicleDefaultsLoading = true;
                    this.vehicleDefaultsError = null;
                    axios.get('/default-vehicle-tasks/active')
                        .then(res => {
                            const data = Array.isArray(res.data?.data) ? res.data.data : [];
                            this.vehicleDefaults = data;
                        })
                        .catch(err => {
                            console.error('Failed to load default vehicle tasks', err);
                            this.vehicleDefaultsError = 'Kon standaard voertuig taken niet laden.';
                        })
                        .finally(() => {
                            this.vehicleDefaultsLoading = false;
                        });
                },
                isDefaultSelected(defaultId) {
                    return this.selectedVehicleTasks.some(v => v.default_id === defaultId);
                },
                toggleDefaultVehicleTask(def) {
                    if (!def || !def.id) return;
                    const idx = this.selectedVehicleTasks.findIndex(v => v.default_id === def.id);
                    if (idx >= 0) {
                        this.selectedVehicleTasks.splice(idx, 1);
                        return;
                    }
                    const item = { default_id: def.id, _default: def, _key: 'd_' + def.id };
                    this.selectedVehicleTasks.push(item);
                },
                addCustomVehicleTask() {
                    const title = (this.customVehicleTask.title || '').trim();
                    if (!title) {
                        alert('Titel is verplicht voor een eigen voertuig taak.');
                        return;
                    }
                    // Prevent duplicates by title (case-insensitive) among customs
                    const exists = this.selectedVehicleTasks.some(v => !v.default_id && (v.title || '').toLowerCase() === title.toLowerCase());
                    if (exists) {
                        alert('Deze voertuig taak staat al in de lijst.');
                        return;
                    }
                    const item = {
                        title: title,
                        description: this.customVehicleTask.description || null,
                        estimated_time_minutes: (this.customVehicleTask.estimated_time_minutes ?? null),
                        _key: 'c_' + Math.random().toString(36).slice(2)
                    };
                    this.selectedVehicleTasks.push(item);
                    // Clear input
                    this.customVehicleTask = { title: '', description: '', estimated_time_minutes: null };
                },
                removeSelectedVehicleTask(index) {
                    if (index >= 0 && index < this.selectedVehicleTasks.length) {
                        this.selectedVehicleTasks.splice(index, 1);
                    }
                },
                buildVehicleTasksPayload() {
                    const payload = { vehicle_tasks: [] };
                    this.selectedVehicleTasks.forEach(vt => {
                        if (vt.default_id) {
                            payload.vehicle_tasks.push({ default_id: vt.default_id });
                        } else {
                            const item = { title: vt.title };
                            if (vt.description) item.description = vt.description;
                            if (vt.estimated_time_minutes != null && vt.estimated_time_minutes !== '') item.estimated_time_minutes = Number(vt.estimated_time_minutes);
                            payload.vehicle_tasks.push(item);
                        }
                    });
                    return payload;
                },
                submitVehicleTasks(planningId) {
                    if (this.selectedVehicleTasks.length === 0) return;
                    const payload = this.buildVehicleTasksPayload();
                    this.submittingVehicleTasks = true;
                    axios.post(`/plannings/${planningId}/vehicle-tasks`, payload)
                        .then(() => {
                            alert('Voertuig taken toegevoegd.');
                            this.selectedVehicleTasks = [];
                        })
                        .catch(error => {
                            console.error('Fout bij toevoegen voertuig taken:', error);
                            const msg = error.response?.data?.message || 'Er is een fout opgetreden bij het toevoegen van voertuig taken.';
                            alert(msg);
                        })
                        .finally(() => {
                            this.submittingVehicleTasks = false;
                        });
                },

                initializeBenodigdhedenChecked() {
                    // Initialize from localStorage if available
                    const savedState = localStorage.getItem(`benodigdheden_checked_${this.planningId}`);
                    if (savedState) {
                        try {
                            this.benodigdhedenChecked = JSON.parse(savedState);
                        } catch (e) {
                            this.benodigdhedenChecked = {};
                        }
                    }

                    // Watch for changes and save to localStorage
                    this.$watch('benodigdhedenChecked', (value) => {
                        localStorage.setItem(`benodigdheden_checked_${this.planningId}`, JSON.stringify(value));
                    }, {
                        deep: true
                    });
                },

                initializeEndChecklistChecked() {
                    // Initialize from localStorage if available
                    const savedState = localStorage.getItem(`end_checklist_checked_${this.planningId}`);
                    if (savedState) {
                        try {
                            this.endChecklistChecked = JSON.parse(savedState);
                        } catch (e) {
                            this.endChecklistChecked = {};
                        }
                    }

                    // Watch for changes and save to localStorage
                    this.$watch('endChecklistChecked', (value) => {
                        localStorage.setItem(`end_checklist_checked_${this.planningId}`, JSON.stringify(value));
                    }, {
                        deep: true
                    });
                },

                getCheckedBenodigdhedenCount() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'requirements') return 0;

                    return current.requirements.filter(benodigdheid =>
                        this.benodigdhedenChecked[benodigdheid.id]
                    ).length;
                },

                getBenodigdhedenProgress() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'requirements' || !current.requirements.length) return 0;

                    const checked = this.getCheckedBenodigdhedenCount();
                    return Math.round((checked / current.requirements.length) * 100);
                },

                areAllBenodigdhedenChecked() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'requirements') return true;

                    return current.requirements.every(benodigdheid =>
                        this.benodigdhedenChecked[benodigdheid.id]
                    );
                },

                getEndChecklistCompletedCount() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'end_checklist') return 0;

                    // For new photo-based checklist, count items with uploaded photos
                    if (current.checklist_items) {
                        return current.checklist_items.filter(item => this.itemHasPhotos(item)).length;
                    }

                    return 0;
                },

                getEndChecklistTotalCount() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'end_checklist') return 0;

                    // For new photo-based checklist, count all items
                    if (current.checklist_items) {
                        return current.checklist_items.length;
                    }

                    return 0;
                },

                getEndChecklistProgress() {
                    const total = this.getEndChecklistTotalCount();
                    if (total === 0) return 100;

                    const completed = this.getEndChecklistCompletedCount();
                    return Math.round((completed / total) * 100);
                },

                areAllEndChecklistItemsChecked() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'end_checklist') return true;

                    // For new photo-based checklist, check if checklist is submitted or approved
                    if (current.checklist_items) {
                        // If checklist is already submitted or approved, user can proceed
                        if (current.has_submitted || current.is_approved) {
                            return true;
                        }
                        // Otherwise, all items must have photos for submission
                        return current.checklist_items.every(item => this.itemHasPhotos(item));
                    }

                    return true;
                },

                // Helpers
                itemHasPhotos(item) {
                    return (item.photos && item.photos.length > 0) || !!item.photo_path || !!item.photo_url;
                },

                getPhotoUrlsForItem(item) {
                    if (item.photos && item.photos.length > 0) {
                        return item.photos.map(p => p.photo_url || p.url || p);
                    }
                    if (item.photo_url) return [item.photo_url];
                    return [];
                },

                // New photo upload functions
                handlePhotoUpload(event, item) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    // Validate files
                    for (const file of files) {
                        if (file.size > 10 * 1024 * 1024) {
                            alert('Bestand is te groot. Maximum 10MB toegestaan.');
                            return;
                        }
                        if (!file.type.startsWith('image/')) {
                            alert('Alleen afbeeldingen zijn toegestaan.');
                            return;
                        }
                    }

                    this.uploadingPhoto = item.id;

                    const formData = new FormData();
                    files.forEach(f => formData.append('photos[]', f));

                    axios.post(`/end-checklist-items/${item.id}/upload-photo`, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })
                        .then(response => {
                            // Ensure item.photos exists
                            if (!Array.isArray(item.photos)) item.photos = [];

                            // Merge new photos (response.photos contains id, file_path, photo_url)
                            const newPhotos = (response.data && response.data.photos) ? response.data.photos : [];

                            // Avoid duplicates by id+file_path
                            const existingKeys = new Set(item.photos.map(p => `${p.id || ''}|${p.file_path || ''}`));
                            newPhotos.forEach(p => {
                                const key = `${p.id || ''}|${p.file_path || ''}`;
                                if (!existingKeys.has(key)) item.photos.push(p);
                            });

                            // Reset the file input
                            event.target.value = '';
                        })
                        .catch(error => {
                            console.error('Upload failed:', error);
                            alert(error.response?.data?.message || 'Er is een fout opgetreden bij het uploaden van de foto\'s.');
                        })
                        .finally(() => {
                            this.uploadingPhoto = null;
                        });
                },

                // Task-like uploader helpers
                draggingItemId: null,

                ensureQueue(item) {
                    if (!item._queued) item._queued = [];
                },

                queuePhotoFiles(event, item) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;
                    this.ensureQueue(item);
                    for (const file of files) {
                        if (file.size > 10 * 1024 * 1024) {
                            alert('Bestand is te groot. Maximum 10MB toegestaan.');
                            continue;
                        }
                        if (!file.type.startsWith('image/')) {
                            alert('Alleen afbeeldingen zijn toegestaan.');
                            continue;
                        }
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            item._queued.push({ file, previewUrl: e.target.result });
                        };
                        reader.readAsDataURL(file);
                    }
                    // Reset input so same files can be chosen again later
                    event.target.value = '';
                },

                handleDrop(e, item) {
                    const dt = e.dataTransfer;
                    if (!dt || !dt.files) return;
                    const event = { target: { files: dt.files } };
                    this.queuePhotoFiles(event, item);
                    this.draggingItemId = null;
                },

                clearQueuedFiles(item) {
                    if (!item._queued) return;
                    item._queued = [];
                },

                removeQueuedFile(item, index) {
                    this.ensureQueue(item);
                    item._queued.splice(index, 1);
                },

                startUploadQueued(item) {
                    this.ensureQueue(item);
                    if (!item._queued.length) return;
                    this.uploadingPhoto = item.id;

                    const formData = new FormData();
                    item._queued.forEach(q => formData.append('photos[]', q.file));

                    axios.post(`/end-checklist-items/${item.id}/upload-photo`, formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    })
                        .then(response => {
                            if (!Array.isArray(item.photos)) item.photos = [];
                            const newPhotos = Array.isArray(response.data?.photos) ? response.data.photos : [];
                            const existingKeys = new Set(item.photos.map(p => `${p.id || ''}|${p.file_path || ''}`));
                            newPhotos.forEach(p => {
                                const key = `${p.id || ''}|${p.file_path || ''}`;
                                if (!existingKeys.has(key)) item.photos.push(p);
                            });
                            // Clear queue after success
                            item._queued = [];
                        })
                        .catch(error => {
                            console.error('Upload failed:', error);
                            alert(error.response?.data?.message || 'Er is een fout opgetreden bij het uploaden van de foto\'s.');
                        })
                        .finally(() => {
                            this.uploadingPhoto = null;
                        });
                },

                removePhoto(item) {
                    if (confirm('Weet je zeker dat je alle foto\'s wilt verwijderen?')) {
                        // Clear the photo data locally
                        item.photo_path = null;
                        item.photo_url = null;
                        item.photos = [];

                        // Call API endpoint to delete all photos from the server
                        axios.delete(`/end-checklist-items/${item.id}/photo`)
                            .then(() => {
                                // successfully deleted
                            })
                            .catch(error => {
                                console.error('Failed to remove photos from server:', error);
                            });
                    }
                },

                removeSinglePhoto(item, photo) {
                    if (!photo || !photo.id) {
                        // If no id available (legacy), fallback to full delete
                        return this.removePhoto(item);
                    }
                    if (!confirm('Weet je zeker dat je deze foto wilt verwijderen?')) return;

                    // Update UI optimistically
                    item.photos = (item.photos || []).filter(p => p.id !== photo.id);

                    axios.delete(`/end-checklist-items/${item.id}/photos/${photo.id}`)
                        .then(() => {
                            // ok
                        })
                        .catch(error => {
                            console.error('Failed to remove photo:', error);
                            alert('Verwijderen van de foto is mislukt.');
                            // Revert by reloading data
                            this.refreshEndChecklistData();
                        });
                },

                canSubmitEndChecklist() {
                    const current = this.currentLocation;
                    if (!current || current.type !== 'end_checklist') return false;

                    // Check if all items have photos
                    if (current.checklist_items) {
                        return current.checklist_items.every(item => this.itemHasPhotos(item));
                    }

                    return false;
                },

                submitEndChecklist(planningId) {
                    if (!this.canSubmitEndChecklist()) {
                        alert('Upload eerst foto\'s voor alle checklist items.');
                        return;
                    }

                    this.submittingEndChecklist = true;
                    axios.post(`/plannings/${planningId}/end-checklist/submit`)
                        .then(response => {
                            // Update the current location data
                            const current = this.currentLocation;
                            if (current) {
                                current.has_submitted = true;
                                current.is_approved = false; // Will be determined by admin
                            }
                            alert('End checklist succesvol ingediend voor beoordeling!');
                        })
                        .catch(error => {
                            console.error('Submit failed:', error);
                            alert(error.response?.data?.message || 'Er is een fout opgetreden bij het indienen van de checklist.');
                        })
                        .finally(() => {
                            this.submittingEndChecklist = false;
                        });
                },

                refreshEndChecklistData() {
                    // Always locate the end_checklist step and refresh it
                    const endStep = this.locationSteps.find(s => s.type === 'end_checklist');
                    if (!endStep) return;

                    axios.get(`/plannings/${endStep.planning_id}/end-checklist`)
                        .then(response => {
                            endStep.checklist_items = response.data.items;
                            endStep.has_submitted = response.data.has_submitted;
                            endStep.is_approved = response.data.is_approved;
                        })
                        .catch(error => {
                            console.error('Failed to refresh checklist data:', error);
                        });
                },

                canProceedToNext() {
                    const current = this.currentLocation;
                    if (!current) return false;

                    if (current.type === 'requirements') {
                        return this.areAllBenodigdhedenChecked();
                    }

                    if (current.type === 'end_checklist') {
                        return this.areAllEndChecklistItemsChecked();
                    }

                    if (current.type === 'location') {
                        return current.tasks ? current.tasks.every(task =>
                            task.status === 'completed' ||
                            task.status === 'review' ||
                            task.status === 'rejected' ||
                            task.status === 'skipped'
                        ) : true;
                    }

                    // Travel steps and call steps can always proceed
                    if (current.type === 'travel' || current.type === 'call' || current.type === 'summary') {
                        return true;
                    }

                    return true;
                },

                getNextButtonText() {
                    if (this.currentLocationIndex === this.locationSteps.length - 1) {
                        const current = this.currentLocation;
                        if (current && current.type === 'end_checklist') {
                            if (current.is_approved) {
                                return 'Planning Voltooien';
                            } else if (current.has_submitted) {
                                return 'Afronden (Wacht op goedkeuring)';
                            } else {
                                return 'Eerst Checklist Indienen';
                            }
                        }
                        return 'Voltooien';
                    }
                    return 'Volgende';
                },

                goToPreviousLocation() {
                    if (this.currentLocationIndex > 0) {
                        this.currentLocationIndex--;
                    }
                },

                async goToNextLocation() {
                    if (this.canProceedToNext()) {
                        const currentLocation = this.currentLocation;
                        // If we are on the end_checklist step and try to move forward, enforce submission first
                        if (currentLocation && currentLocation.type === 'end_checklist') {
                            if (!currentLocation.has_submitted) {
                                alert('Je moet eerst alle foto\'s uploaden en de eind checklist indienen voordat je doorgaat.');
                                return;
                            }
                        }

                        // Handle final step (end checklist completion)
                        if (this.currentLocationIndex === this.locationSteps.length - 1) {
                            // If the final step is end_checklist, keep legacy behavior
                            if (currentLocation && currentLocation.type === 'end_checklist') {
                                if (!currentLocation.has_submitted) {
                                    alert('Je moet eerst alle foto\'s uploaden en de checklist indienen.');
                                    return;
                                }

                                if (!currentLocation.is_approved) {
                                    // Planning is submitted but not approved - show completion message
                                    this.currentLocationIndex++;
                                    return;
                                }
                            } else {
                                // If final step is not end_checklist, but an end_checklist exists and is not approved
                                const endStep = this.locationSteps.find(s => s.type === 'end_checklist');
                                if (endStep) {
                                    if (!endStep.has_submitted) {
                                        alert('Dien eerst de eind checklist in voordat je afrondt.');
                                        return;
                                    }
                                    if (!endStep.is_approved) {
                                        // Allow proceeding to waiting screen
                                        this.currentLocationIndex++;
                                        return;
                                    }
                                }
                            }

                            // If the last step is a travel step (e.g., return trip), persist its timer before completing
                            if (currentLocation && currentLocation.type === 'travel') {
                                await this.stopLocationTimer(true);
                            }

                            // Complete the planning
                            this.currentLocationIndex++;
                            return;
                        }

                        // Continue to next step
                        if (this.currentLocationIndex < this.locationSteps.length - 1) {
                            // Determine if we should save the timer
                            let shouldSaveTimer = false;
                            if (currentLocation.type === 'location' && this.areAllTasksCompleted(currentLocation)) {
                                shouldSaveTimer = true;
                            } else if (currentLocation.type === 'travel') {
                                // Always save travel timers when completed
                                shouldSaveTimer = true;
                            } else if (currentLocation.type === 'call') {
                                // For call steps, we need to stop the timer of the previous location
                                // Find the previous location that was actually visited
                                const previousLocationStep = this.findPreviousLocationStep();
                                if (previousLocationStep) {
                                    await this.stopSpecificLocationTimer(previousLocationStep, true);
                                }
                            }

                            if (shouldSaveTimer) {
                                await this.stopLocationTimer(true);
                            } else {
                                this.stopLocationTimer(false);
                            }
                            this.currentLocationIndex++;
                        }
                    }
                },

                isCompleted() {
                    return this.currentLocationIndex === this.locationSteps.length;
                },

                areAllTasksCompleted(location) {
                    if (!location.tasks) return true;
                    return location.tasks.every(task =>
                        task.status === 'completed' ||
                        task.status === 'review'
                    );
                },

                async startLocationTimer() {
                    this.stopLocationTimer(false); // Stop zonder opslaan

                    const currentLocation = this.currentLocation;
                    if (!currentLocation || (currentLocation.type !== 'location' && currentLocation.type !== 'travel')) return;

                    let timerId;
                    if (currentLocation.type === 'travel') {
                        timerId = currentLocation.travel_id || `travel_to_${currentLocation.destination_location_id}`;
                    } else {
                        timerId = currentLocation.location_id || 'backlog';
                    }

                    try {
                        // Haal timer data op uit database
                        const response = await axios.get(`/plannings/${this.planningId}/locations/${timerId}/timer`);
                        const timerData = response.data;

                        if (timerData.started_at && !timerData.ended_at) {
                            // Timer loopt al - hervat vanaf huidige staat
                            this.locationStartTime = new Date(timerData.started_at).getTime();
                            this.locationBaseDuration = timerData.total_duration || 0;
                            this.locationElapsedSeconds = this.locationBaseDuration + Math.floor((Date.now() - this.locationStartTime) / 1000);
                            console.log(`Timer hervat voor ${currentLocation.title || currentLocation.location_name}`);
                        } else if (!timerData.started_at) {
                            // Eerste keer - start nieuwe timer vanaf 0
                            this.locationStartTime = Date.now();
                            this.locationBaseDuration = 0;
                            this.locationElapsedSeconds = 0;

                            // Start timer in database
                            await axios.post(`/plannings/${this.planningId}/locations/${timerId}/timer/start`);
                            console.log(`Timer gestart voor ${currentLocation.title || currentLocation.location_name}`);
                        } else if (timerData.ended_at) {
                            // Timer was beëindigd - herstart en behoud de tijd
                            this.locationStartTime = Date.now();
                            this.locationBaseDuration = timerData.total_duration || 0;
                            this.locationElapsedSeconds = this.locationBaseDuration;

                            // Herstart timer in database
                            await axios.post(`/plannings/${this.planningId}/locations/${timerId}/timer/start`);

                            console.log(`Timer herstart voor ${currentLocation.title || currentLocation.location_name} vanaf ${this.formatDuration(this.locationBaseDuration)}`);
                        } else {
                            // Onbekende staat - start opnieuw
                            this.locationStartTime = Date.now();
                            this.locationBaseDuration = timerData.total_duration || 0;
                            this.locationElapsedSeconds = this.locationBaseDuration;

                            // Start timer in database
                            await axios.post(`/plannings/${this.planningId}/locations/${timerId}/timer/start`);
                            console.log(`Timer gestart voor ${currentLocation.title || currentLocation.location_name}`);
                        }
                    } catch (error) {
                        console.warn('Kon timer data niet ophalen, gebruik fallback:', error);
                        // Fallback naar localStorage - nooit resetten naar 0
                        const storageKey = `location_timer_${this.planningId}_${timerId}`;
                        const savedData = localStorage.getItem(storageKey);

                        if (savedData) {
                            try {
                                const timerData = JSON.parse(savedData);
                                this.locationStartTime = Date.now();
                                this.locationBaseDuration = timerData.totalDuration || 0;
                                this.locationElapsedSeconds = this.locationBaseDuration;
                            } catch (e) {
                                // Old format fallback
                                this.locationStartTime = parseInt(savedData);
                                this.locationBaseDuration = Math.floor((Date.now() - this.locationStartTime) / 1000);
                                this.locationElapsedSeconds = this.locationBaseDuration;
                            }
                        } else {
                            // Alleen bij eerste bezoek starten vanaf 0
                            this.locationStartTime = Date.now();
                            this.locationBaseDuration = 0;
                            this.locationElapsedSeconds = 0;
                            localStorage.setItem(storageKey, JSON.stringify({
                                startTime: this.locationStartTime,
                                totalDuration: 0
                            }));
                        }
                    }

                    // Start de UI timer
                    this.locationTimer = setInterval(() => {
                        if (this.locationStartTime) {
                            const currentSessionSeconds = Math.floor((Date.now() - this.locationStartTime) / 1000);
                            this.locationElapsedSeconds = this.locationBaseDuration + currentSessionSeconds;
                        }
                    }, 1000);
                },

                async stopLocationTimer(saveToDatabase = false) {
                    if (this.locationTimer) {
                        clearInterval(this.locationTimer);
                        this.locationTimer = null;
                    }

                    const currentLocation = this.currentLocation;
                    if (currentLocation && (currentLocation.type === 'location' || currentLocation.type === 'travel')) {
                        let timerId;
                        if (currentLocation.type === 'travel') {
                            timerId = currentLocation.travel_id || `travel_to_${currentLocation.destination_location_id}`;
                        } else {
                            timerId = currentLocation.location_id || 'backlog';
                        }

                        // Altijd de huidige tijd opslaan in localStorage (als backup)
                        const storageKey = `location_timer_${this.planningId}_${timerId}`;
                        const totalDuration = this.locationElapsedSeconds;
                        localStorage.setItem(storageKey, JSON.stringify({
                            startTime: Date.now(),
                            totalDuration: totalDuration
                        }));

                        if (saveToDatabase && this.locationStartTime) {
                            const currentSessionDuration = Math.floor((Date.now() - this.locationStartTime) / 1000);
                            const finalTotalDuration = this.locationBaseDuration + currentSessionDuration;

                            try {
                                await axios.post(`/plannings/${this.planningId}/locations/${timerId}/timer/stop`, {
                                    total_duration: finalTotalDuration
                                });

                                console.log(`Timer opgeslagen voor ${currentLocation.title || currentLocation.location_name}: ${this.formatDuration(finalTotalDuration)}`);
                            } catch (error) {
                                console.error('Kon timer niet opslaan in database:', error);
                            }
                        }
                    }

                    // Timer state alleen resetten als we echt opslaan (locatie verlaten)
                    if (saveToDatabase) {
                        this.locationStartTime = null;
                        this.locationBaseDuration = 0;
                        // elapsedSeconds behouden voor weergave
                    } else {
                        // Bij normale navigatie: timer pauzeren maar niet resetten
                        this.locationStartTime = null;
                        // baseDuration en elapsedSeconds behouden
                    }
                },

                findPreviousLocationStep() {
                    // Find the most recent location or backlog step before the current call step
                    for (let i = this.currentLocationIndex - 1; i >= 0; i--) {
                        const step = this.locationSteps[i];
                        if (step.type === 'location') {
                            return step;
                        }
                    }
                    return null;
                },

                async stopSpecificLocationTimer(locationStep, saveToDatabase = false) {
                    if (!locationStep) return;

                    let timerId;
                    if (locationStep.type === 'travel') {
                        timerId = locationStep.travel_id || `travel_to_${locationStep.destination_location_id}`;
                    } else {
                        timerId = locationStep.location_id || 'backlog';
                    }

                    if (saveToDatabase) {
                        try {
                            // Get current timer data to calculate total duration
                            const response = await axios.get(`/plannings/${this.planningId}/locations/${timerId}/timer`);
                            const timerData = response.data;

                            if (timerData.started_at && !timerData.ended_at) {
                                const startTime = new Date(timerData.started_at).getTime();
                                const totalDuration = (timerData.total_duration || 0) + Math.floor((Date.now() - startTime) / 1000);

                                await axios.post(`/plannings/${this.planningId}/locations/${timerId}/timer/stop`, {
                                    total_duration: totalDuration
                                });

                                // Update localStorage with final duration (don't remove)
                                const storageKey = `location_timer_${this.planningId}_${timerId}`;
                                localStorage.setItem(storageKey, JSON.stringify({
                                    startTime: Date.now(),
                                    totalDuration: totalDuration
                                }));

                                console.log(`Timer gestopt voor ${locationStep.location_name || locationStep.title}: ${this.formatDuration(totalDuration)}`);
                            }
                        } catch (error) {
                            console.error('Kon timer niet stoppen voor vorige locatie:', error);
                        }
                    }
                },

                getLocationDuration() {
                    if (this.locationStartTime) {
                        return Math.floor((Date.now() - this.locationStartTime) / 1000);
                    }
                    return 0;
                },

                formatDuration(seconds) {
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = seconds % 60;

                    if (hours > 0) {
                        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                    }
                    return `${minutes}:${secs.toString().padStart(2, '0')}`;
                },

                getStepDisplay() {
                    const totalSteps = this.locationSteps.length;
                    let currentStep = this.currentLocationIndex + 1;

                    // If completed and end checklist is waiting approval, show max step
                    if (this.isCompleted() && this.isEndChecklistWaitingApproval()) {
                        currentStep = totalSteps;
                    }

                    // Never show more than totalSteps
                    currentStep = Math.min(currentStep, totalSteps);

                    return `${currentStep}/${totalSteps}`;
                },

                getProgressPercentage() {
                    const totalSteps = this.locationSteps.length;
                    let currentStep = this.currentLocationIndex + 1;

                    // If completed and end checklist is waiting approval, show 100%
                    if (this.isCompleted() && this.isEndChecklistWaitingApproval()) {
                        return 100;
                    }

                    // Never show more than 100%
                    const percentage = Math.min((currentStep / totalSteps) * 100, 100);
                    return Math.round(percentage);
                },
                isEndChecklistWaitingApproval() {
                    const endStep = this.locationSteps.find(s => s.type === 'end_checklist');
                    if (!endStep) return false;
                    return !!endStep.has_submitted && !endStep.is_approved;
                },

                playAnimation() {
                    const animationContainer = document.getElementById('lottie-animation');
                    if (!animationContainer || animationContainer.childElementCount > 0) return;

                    lottie.loadAnimation({
                        container: animationContainer,
                        renderer: 'svg',
                        loop: false,
                        autoplay: true,
                        path: 'https://cdn.prod.website-files.com/5d829bf092d4644f5c42e0ea/5def871cca4d3b3d86d6ee1b_Success-Pack9-smooth.json'
                    });
                }
            }));
        });
    </script>
    @endpush

    @push('styles')
    <style>
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    @endpush
</x-app-layout>
