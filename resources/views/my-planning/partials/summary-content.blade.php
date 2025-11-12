@if($planning->locations->count() > 0)
    <div class="space-y-4">
        @if($planning->start_address)
            <div class="flex items-center text-sm">
                <div class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-medium">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $planning->start_address }}</span>
                    @if($planning->start_time)
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ \Carbon\Carbon::parse($planning->start_time)->format('H:i') }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Vehicle info --}}
        <div class="flex items-center text-sm">
            <div class="flex-shrink-0 w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs font-medium">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h13V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6zm13 0h3l3 3v2a1 1 0 01-1 1h-2m-3-6v6M6 19h.01M10 19h.01" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-gray-100">Voertuig:</span>
                @if($planning->vehicle)
                    <span class="text-gray-900 dark:text-gray-100">{{ $planning->vehicle->name }}</span>
                    @if(!empty($planning->vehicle->license_number))
                        <span class="text-gray-500 dark:text-gray-400">({{ $planning->vehicle->license_number }})</span>
                    @endif
                @else
                    <span class="text-gray-500 dark:text-gray-400">Geen voertuig gekoppeld</span>
                @endif
            </div>
        </div>

        @foreach($planning->locations as $locationIndex => $location)
            @php
                // Get tasks for this location
                $tasksForLocation = $planning->planningTasks->filter(function ($pt) use ($location) {
                    if ($pt->task_id && $pt->task) { // Backlog Task
                        return $pt->task->location_id == $location->id;
                    } elseif ($pt->default_task_id && $pt->defaultTask) { // Default Task
                        return $pt->location_id == $location->id;
                    }
                    return false;
                });

                $totalMinutesForLocation = 0;
                foreach ($tasksForLocation as $planningTask) {
                    $estimatedMinutes = 0;
                    if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                        $estimatedMinutes = (int)$planningTask->task->estimated_time_minutes;
                    } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                       $estimatedMinutes = (int)$planningTask->defaultTask->estimated_time_minutes;
                    }
                    $totalMinutesForLocation += $estimatedMinutes;
                }
            @endphp

            {{-- Travel time to this location --}}
            @if($travelTimes && isset($travelTimes['segments'][$locationIndex]) && ($locationIndex > 0 || $planning->start_address))
                <div class="ml-3 flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span>
                        <span class="font-medium">{{ $travelTimes['segments'][$locationIndex]['duration_minutes'] }} min</span> naar {{ $location->name }}
                    </span>
                    @if($travelTimes['segments'][$locationIndex]['distance_km'] > 0)
                        <span class="ml-1 text-gray-400">({{ $travelTimes['segments'][$locationIndex]['distance_km'] }} km)</span>
                    @endif
                </div>
            @endif

            {{-- Location --}}
            <div class="flex items-start text-sm">
                <div class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-medium mt-0.5">
                    {{ $planning->start_address ? $locationIndex + 1 : $locationIndex + 1 }}
                </div>
                <div class="ml-3 flex-1">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $location->name }}</div>

                    {{-- Tasks for this location --}}
                    @if($tasksForLocation->count() > 0)
                        <div class="mt-2 ml-4 space-y-1">
                            @foreach($tasksForLocation as $planningTask)
                                @php
                                    $estimatedMinutes = 0;
                                    if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                                        $estimatedMinutes = (int)$planningTask->task->estimated_time_minutes;
                                    } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                                       $estimatedMinutes = (int)$planningTask->defaultTask->estimated_time_minutes;
                                    }
                                @endphp
                                <div class="flex items-center text-xs text-gray-600 dark:text-gray-400">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                    <span class="flex-1">{{ $planningTask->title }}</span>
                                    @if($estimatedMinutes > 0)
                                        <span class="font-medium text-gray-500 dark:text-gray-500">{{ $estimatedMinutes }} min</span>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Total time for location --}}
                            @if($totalMinutesForLocation > 0)
                                <div class="mt-1 pt-1 border-t border-gray-200 dark:border-gray-600">
                                    <div class="flex items-center text-xs font-medium text-gray-700 dark:text-gray-300">
                                        <span class="flex-1">Totaal locatie:</span>
                                        <span>{{ $totalMinutesForLocation }} min</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Geen taken gepland</div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Return trip if exists --}}
        @if($travelTimes && count($travelTimes['segments']) > $planning->locations->count())
            @php
                $returnSegment = $travelTimes['segments'][count($travelTimes['segments']) - 1];
            @endphp
            @if(isset($returnSegment['is_return']) && $returnSegment['is_return'])
                <div class="ml-3 flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span>
                        <span class="font-medium">{{ $returnSegment['duration_minutes'] }} min</span> terug naar {{ $returnSegment['to'] }}
                        <span class="text-green-600">(terug)</span>
                    </span>
                    @if($returnSegment['distance_km'] > 0)
                        <span class="ml-1 text-gray-400">({{ $returnSegment['distance_km'] }} km)</span>
                    @endif
                </div>

                <div class="flex items-center text-sm">
                    <div class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-medium">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $returnSegment['to'] }} (terug)</span>
                    </div>
                </div>
            @endif
        @endif
    </div>
@else
    <p class="text-gray-500 dark:text-gray-400">Geen locaties gepland voor vandaag.</p>
@endif

{{-- Time Overview --}}
@if($timeOverview['total_minutes'] > 0)
    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Tijdoverzicht</h4>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Taken</p>
                        <p class="text-lg font-bold text-green-900 dark:text-green-100">
                            {{ $timeOverview['task_minutes'] < 60 ? $timeOverview['task_minutes'] . ' min' : intval($timeOverview['task_minutes'] / 60) . 'u ' . ($timeOverview['task_minutes'] % 60) . 'm' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Reistijd</p>
                        <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                            {{ $timeOverview['travel_minutes'] < 60 ? $timeOverview['travel_minutes'] . ' min' : intval($timeOverview['travel_minutes'] / 60) . 'u ' . ($timeOverview['travel_minutes'] % 60) . 'm' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-purple-800 dark:text-purple-200">Totaal</p>
                        <p class="text-lg font-bold text-purple-900 dark:text-purple-100">
                            {{ $timeOverview['total_minutes'] < 60 ? $timeOverview['total_minutes'] . ' min' : intval($timeOverview['total_minutes'] / 60) . 'u ' . ($timeOverview['total_minutes'] % 60) . 'm' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
