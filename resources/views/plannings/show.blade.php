<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{-- Planning Details: {{ $planning->location->name }} op {{ $planning->planned_date->format('d-m-Y') }} --}}
                Planning Details: {{ $planning->locations->pluck('name')->join(', ') ?: 'Nog geen locatie(s)' }} op {{ $planning->planned_date->format('d-m-Y') }}
            </h2>
            <div class="flex-shrink-0">
                @if($planning->users->contains(Auth::user()))
                    <a href="{{ route('my-planning.planning', $planning) }}" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 mr-2 text-sm font-medium">
                        Start Planning
                    </a>
                @endif
                @can('update', $planning)
                    <a href="{{ route('plannings.edit', $planning) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-700 mr-2 text-sm font-medium">
                        Bewerken
                    </a>
                    @if($planning->users->isNotEmpty())
                        <form method="POST" action="{{ route('plannings.send-notifications', $planning) }}" class="inline-block mr-2">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-sm font-medium"
                                    onclick="return confirm('Weet je zeker dat je alle gebruikers van deze planning wilt notificeren?')">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Stuur notificaties
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('plannings.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 text-sm font-medium">
                    Terug naar overzicht
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            @php
                $onLocationTimers = $onLocationTimers ?? collect();
                $travelToTimers = $travelToTimers ?? collect();
                $travelBackTimer = $travelBackTimer ?? null;
                $actualTotals = $actualTotals ?? ['travel_seconds' => 0, 'on_location_seconds' => 0];
            @endphp

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-100 rounded-md shadow-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-100 rounded-md shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Geplande Datum</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->planned_date->format('l, j F Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @switch(strtolower($planning->status ?? ''))
                                        @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                        @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                        @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                        @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                    @endswitch
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $planning->status ?? 'Onbekend')) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Aangemaakt op</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Aangemaakt door</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->creator?->name ?? 'Onbekend' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Uitvoerders</h3>
                            <div class="mt-1">
                                @if($planning->users->count() > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($planning->users as $user)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                {{ $user->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-lg text-gray-500 dark:text-gray-400">Geen uitvoerders toegewezen</p>
                                @endif
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Startpunt</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->start_address }}</p>
                        </div>
                        @if($planning->start_time)
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Starttijd</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($planning->start_time)->format('H:i') }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Route & Reistijden sectie --}}
                    @if($planning->locations->count() > 0)
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Samenvatting</h3>

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
                                        </div>
                                    </div>
                                @endif

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

                                    {{-- Travel time to this location (planned) --}}
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
                                            {{ $locationIndex + 1 }}
                                        </div>
                                        <div class="ml-3 flex-1">

                                            <div class="flex justify-between items-start">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $location->name }}</div>
                                                @php
                                                    $timer = $onLocationTimers->get($location->id);
                                                    $timerSeconds = (int)($timer->total_duration_seconds ?? 0);
                                                    $hours = floor($timerSeconds / 3600);
                                                    $minutes = floor(($timerSeconds % 3600) / 60);
                                                    $seconds = $timerSeconds % 60;
                                                @endphp
                                                @if($timerSeconds > 0)
                                                    @if(Auth::user()->isAdmin())
                                                        <div class="text-right">
                                                            <div class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">
                                                                @if($hours > 0)
                                                                    {{ sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) }}
                                                                @else
                                                                    {{ sprintf('%02d:%02d', $minutes, $seconds) }}
                                                                @endif
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                @if($timer && $timer->started_at && !$timer->ended_at)
                                                                    <span class="inline-flex items-center">
                                                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></span>
                                                                        Actief
                                                                    </span>
                                                                @else
                                                                    Gewerkte tijd
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>

                                            {{-- Timers (editable) --}}
                                            @php
                                                $locTimer = $onLocationTimers->get($location->id);
                                                $locSec = (int)($locTimer->total_duration_seconds ?? 0);
                                                $locHH = intdiv($locSec, 3600);
                                                $locMM = intdiv($locSec % 3600, 60);
                                            @endphp
                                            @include('plannings.partials.time_row', [
                                                'label' => 'Werkelijke tijd op locatie:',
                                                'display' => sprintf('%02d:%02d', $locHH, $locMM),
                                                'action' => route('plannings.timers.location.update', [$planning, $location]),
                                                'ariaLabel' => "Tijd op locatie {$location->name} in HH:mm",
                                                'canEdit' => Auth::user()->isAdmin() || Auth::user()->canManagePlannings(),
                                            ])

                                            @if($travelTimes && isset($travelTimes['segments'][$locationIndex]) && ($locationIndex > 0 || $planning->start_address))
                                                @php
                                                    $segTimer = $travelToTimers->get($location->id);
                                                    $segSec = (int)($segTimer->total_duration_seconds ?? 0);
                                                    $segHH = intdiv($segSec, 3600);
                                                    $segMM = intdiv($segSec % 3600, 60);
                                                @endphp
                                                @include('plannings.partials.time_row', [
                                                    'label' => "Werkelijke reistijd naar {$location->name}:",
                                                    'display' => sprintf('%02d:%02d', $segHH, $segMM),
                                                    'action' => route('plannings.timers.travel_to.update', [$planning, $location]),
                                                    'ariaLabel' => "Reistijd naar {$location->name} in HH:mm",
                                                    'canEdit' => Auth::user()->isAdmin() || Auth::user()->canManagePlannings(),
                                                ])
                                            @endif

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

                                        {{-- Actual return travel time (editable) --}}
                                        @php
                                            $retSec = (int)($travelBackTimer->total_duration_seconds ?? 0);
                                            $retHH = intdiv($retSec, 3600);
                                            $retMM = intdiv($retSec % 3600, 60);
                                        @endphp
                                        @include('plannings.partials.time_row', [
                                            'label' => 'Werkelijke reistijd terug:',
                                            'display' => sprintf('%02d:%02d', $retHH, $retMM),
                                            'action' => route('plannings.timers.travel_back.update', [$planning]),
                                            'ariaLabel' => 'Reistijd terug in HH:mm',
                                            'canEdit' => Auth::user()->isAdmin() || Auth::user()->canManagePlannings(),
                                        ])
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($planning->notes)
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Notities/instructies</h3>
                            <p class="mt-1 text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $planning->notes }}</p>
                        </div>
                    @endif

                    {{-- Time Overview --}}
                    @if($timeOverview['total_minutes'] > 0)
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Tijdoverzicht</h3>

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
                </div>
            </div>

            @if ($planning->locations->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-center text-gray-500 dark:text-gray-400">Geen locaties toegewezen aan deze planning.</p>
                </div>
            @else
                @foreach ($planning->locations as $location)
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Taken voor Locatie: {{ $location->name }}</h3>
                        </div>

                        @php
                            $tasksForLocation = $planning->planningTasks->filter(function ($pt) use ($location) {
                                if ($pt->task_id && $pt->task) { // Backlog Task
                                    return $pt->task->location_id == $location->id;
                                } elseif ($pt->default_task_id && $pt->defaultTask) { // Default Task
                                    return $pt->location_id == $location->id; // Use direct location_id on PlanningTask
                                }
                                return false;
                            });

                            $priorityOrder = [
                                'high' => 1,
                                'normal' => 2,
                                'low' => 3,
                            ];

                            $tasksForLocation = $tasksForLocation->sortBy(function ($planningTask) use ($priorityOrder) {
                                // Backlog tasks have priority
                                if ($planningTask->task && $planningTask->task->priority) {
                                    return $priorityOrder[$planningTask->task->priority->value] ?? 4;
                                }
                                // Default tasks are considered last
                                return 4;
                            });

                            $totalMinutesForLocation = 0; // Initialize total minutes for this location
                        @endphp

                        @if ($tasksForLocation->isEmpty())
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <p class="text-center text-gray-500 dark:text-gray-400">Geen taken gepland voor deze locatie.</p>
                            </div>
                        @else
                            <div class="flex flex-col">
                                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Taak</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Prioriteit</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Gesch. Tijd</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Status</th>
                                                        <th scope="col" class="relative py-3.5 px-4">
                                                            <span class="sr-only">Acties</span>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                                    @foreach ($tasksForLocation as $planningTask)
                                                        @php
                                                            $estimatedMinutes = 0;
                                                            if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                                                                $estimatedMinutes = (int)$planningTask->task->estimated_time_minutes;
                                                            } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                                                               $estimatedMinutes = (int)$planningTask->defaultTask->estimated_time_minutes;
                                                            }
                                                            $totalMinutesForLocation += $estimatedMinutes;
                                                        @endphp
                                                        <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                            <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-normal">
                                                                @if ($planningTask->task_id)
                                                                    <a href="{{ route('tasks.show', ['task' => $planningTask->task, 'planning' => $planning->id]) }}" class="font-semibold hover:underline">{{ $planningTask->title }}</a>
                                                                @else
                                                                    <a href="{{ route('plannings.tasks.show', $planningTask) }}" class="font-semibold hover:underline">{{ $planningTask->title }}</a>
                                                                @endif
                                                                @if($planningTask->description)
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($planningTask->description, 150) }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                                @if ($planningTask->task && $planningTask->task->priority)
                                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full {{
                                                                        match($planningTask->task->priority) {
                                                                            App\Enums\TaskPriority::HIGH => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                            App\Enums\TaskPriority::NORMAL => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                                            App\Enums\TaskPriority::LOW => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                                            default => 'bg-gray-100 text-gray-600'
                                                                        }
                                                                    }}">{{ $planningTask->task->priority->label() }}</span>
                                                                @elseif ($planningTask->defaultTask) {{-- Default tasks don't have priority shown here --}}
                                                                    -
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                                {{ $estimatedMinutes > 0 ? $estimatedMinutes . ' min' : 'N/A' }}
                                                            </td>
                                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                                    @php
                                                                        $statusValue = is_object($planningTask->status) ? $planningTask->status->value : $planningTask->status;
                                                                    @endphp
                                                                    @switch($statusValue)
                                                                        @case('review') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 @break
                                                                        @case('completed') @if($planningTask->completed_at) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @endif @break
                                                                        @default bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                                                    @endswitch
                                                                ">
                                                                    @if($planningTask->completed_at)
                                                                        {{ $planningTask->status === App\Enums\TaskStatus::COMPLETED || $planningTask->status === 'completed' ? 'Voltooid' : (is_object($planningTask->status) ? $planningTask->status->label() : ucfirst($planningTask->status)) }}
                                                                    @else
                                                                        Openstaand
                                                                    @endif
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-right align-top">
                                                                @php
                                                                    $statusValue = is_object($planningTask->status) ? $planningTask->status->value : $planningTask->status;
                                                                @endphp
                                                                @if ($statusValue === 'review')
                                                                    @php
                                                                        $latestCompletion = $planningTask->completions->first();
                                                                        $photoUrls = $latestCompletion ? $latestCompletion->photos->pluck('url')->all() : [];
                                                                    @endphp
                                                                    @if($latestCompletion && $latestCompletion->comment)
                                                                        <div class="mb-2 text-right">
                                                                            <div class="text-left text-sm text-gray-500 dark:text-gray-400 block max-w-xs md:max-w-sm lg:max-w-md break-anywhere">Notities: {{ \Illuminate\Support\Str::limit($latestCompletion->comment, 100) }}</div>
                                                                            <button type="button"
                                                                                x-data
                                                                                x-on:click.prevent="$dispatch('open-modal', 'view-comment-{{ $planningTask->id }}')"
                                                                                class="mt-1 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium">
                                                                                Lees volledig
                                                                            </button>
                                                                        </div>
                                                                        <x-modal name="view-comment-{{ $planningTask->id }}" :show="$errors->isNotEmpty()" focusable>
                                                                            <div class="p-6">
                                                                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-left">Notities</h3>
                                                                                <div class="mt-4 max-h-64 overflow-y-auto overscroll-contain text-left">
                                                                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-anywhere">{{ $latestCompletion->comment }}</p>
                                                                                </div>
                                                                                <div class="mt-6 text-right">
                                                                                    <x-secondary-button x-on:click="$dispatch('close')">
                                                                                        Sluiten
                                                                                    </x-secondary-button>
                                                                                </div>
                                                                            </div>
                                                                        </x-modal>
                                                                    @endif
                                                                    @if (!empty($photoUrls))
                                                                        <div class="mb-2">
                                                                            <div class="flex items-center justify-end gap-2">
                                                                                @foreach (array_slice($photoUrls, 0, 3) as $idx => $url)
                                                                                    <button type="button"
                                                                                            class="block w-14 h-14 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 hover:opacity-90"
                                                                                            x-data="{}"
                                                                                            @click="$dispatch('open-image-modal', { imageUrls: @js($photoUrls), startIndex: {{ $idx }} })">
                                                                                        <img src="{{ $url }}" alt="Bewijsfoto" class="w-full h-full object-cover">
                                                                                    </button>
                                                                                @endforeach
                                                                                @if (count($photoUrls) > 3)
                                                                                    <button type="button"
                                                                                            class="relative block w-14 h-14 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 hover:opacity-90"
                                                                                            x-data="{}"
                                                                                            @click="$dispatch('open-image-modal', { imageUrls: @js($photoUrls), startIndex: 3 })">
                                                                                        <img src="{{ $photoUrls[3] }}" alt="Meer bewijdfoto's" class="w-full h-full object-cover opacity-70">
                                                                                        <span class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-white bg-black/50">+{{ count($photoUrls) - 3 }}</span>
                                                                                    </button>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                    @if (Auth::user()->isAdmin())
                                                                        <div class="flex flex-wrap items-center justify-end gap-2 sm:flex-nowrap">
                                                                            <form action="{{ route('plannings.tasks.approve', $planningTask) }}" method="POST">
                                                                                @csrf
                                                                                <input type="hidden" name="planning_id" value="{{ $planning->id }}">
                                                                                <x-primary-button type="submit" class="!py-1 !px-2 !text-xs">Goedkeuren</x-primary-button>
                                                                            </form>
                                                                            <x-danger-button
                                                                                x-data
                                                                                x-on:click.prevent="$dispatch('open-modal', 'reject-task-{{ $planningTask->id }}')"
                                                                                class="!py-1 !px-2 !text-xs"
                                                                            >Afkeuren</x-danger-button>

                                                                            <x-modal name="reject-task-{{ $planningTask->id }}" :show="$errors->isNotEmpty()" focusable>
                                                                                <form action="{{ route('plannings.tasks.reject', $planningTask) }}" method="POST" class="p-6">
                                                                                    @csrf
                                                                                    <input type="hidden" name="planning_id" value="{{ $planning->id }}">

                                                                                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                                                        {{ __('Taak Afkeuren: ') . $planningTask->title }}
                                                                                    </h2>

                                                                                    <div class="mt-6">
                                                                                        <x-input-label for="review_notes_{{ $planningTask->id }}" value="{{ __('Reden van afkeuring (verplicht)') }}" />
                                                                                        <textarea
                                                                                            id="review_notes_{{ $planningTask->id }}"
                                                                                            name="review_notes"
                                                                                            required
                                                                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                                                        >{{ old('review_notes') }}</textarea>
                                                                                        <x-input-error class="mt-2" :messages="$errors->get('review_notes')" />
                                                                                    </div>

                                                                                    <div class="mt-6 flex justify-end">
                                                                                        <x-secondary-button x-on:click="$dispatch('close')">
                                                                                            {{ __('Annuleren') }}
                                                                                        </x-secondary-button>
                                                                                        <x-danger-button class="ml-3">
                                                                                            {{ __('Definitief afkeuren') }}
                                                                                        </x-danger-button>
                                                                                    </div>
                                                                                </form>
                                                                            </x-modal>
                                                                        </div>
                                                                    @endif
                                                                @elseif (!$planningTask->completed_at)
                                                                    <x-primary-button
                                                                        x-data=""
                                                                        x-on:click.prevent="$dispatch('open-modal', 'complete-task-{{ $planningTask->id }}')">
                                                                        {{ __('Voltooien') }}
                                                                    </x-primary-button>
                                                                    <x-modal name="complete-task-{{ $planningTask->id }}" :show="$errors->isNotEmpty()" focusable>
                                                                        <form method="post" action="{{ route('plannings.tasks.complete', [$planning, $planningTask]) }}" class="p-6" enctype="multipart/form-data">
                                                                            @csrf

                                                                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                                                {{ __('Taak Voltooien: ') . $planningTask->title }}
                                                                            </h2>

                                                                            <div class="mt-6">
                                                                                <x-input-label for="completed_notes_{{ $planningTask->id }}" value="{{ __('Opmerking') }}" />
                                                                                <textarea name="completed_notes" id="completed_notes_{{ $planningTask->id }}" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>{{ old('completed_notes') }}</textarea>
                                                                                <x-input-error class="mt-2" :messages="$errors->get('completed_notes')" />
                                                                            </div>

                                                                            <div class="mt-6">
                                                                                <x-input-label for="photos_{{ $planningTask->id }}" value="{{ __('Fotos (minimaal 1)') }}" />
                                                                                <input type="file" name="photos[]" id="photos_{{ $planningTask->id }}" multiple @if(!Auth::user()->isAdmin()) required @endif class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                                                                                <x-input-error class="mt-2" :messages="$errors->get('photos.*')" />
                                                                                <x-input-error class="mt-2" :messages="$errors->get('photos')" />
                                                                            </div>

                                                                            <div class="mt-6">
                                                                                <label for="is_fully_completed_{{ $planningTask->id }}" class="inline-flex items-center">
                                                                                    <input type="hidden" name="is_fully_completed" value="0">
                                                                                    <input id="is_fully_completed_{{ $planningTask->id }}" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-indigo-500" name="is_fully_completed" value="1" checked>
                                                                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">{{ __('Taak is volledig voltooid') }}</span>
                                                                                </label>
                                                                            </div>

                                                                            <div class="mt-6 flex justify-end">
                                                                                <x-secondary-button x-on:click="$dispatch('close')">
                                                                                    {{ __('Annuleren') }}
                                                                                </x-secondary-button>

                                                                                <x-primary-button class="ml-3">
                                                                                    {{ __('Taak afronden') }}
                                                                                </x-primary-button>
                                                                            </div>
                                                                        </form>
                                                                    </x-modal>
                                                                @else
                                                                    <x-danger-button
                                                                        x-data=""
                                                                        x-on:click.prevent="$dispatch('open-modal', 'reopen-task-{{ $planningTask->id }}')">
                                                                        {{ __('Heropenen') }}
                                                                    </x-danger-button>

                                                                    <x-modal name="reopen-task-{{ $planningTask->id }}" :show="$errors->isNotEmpty()" focusable>
                                                                        <form method="post" action="{{ route('plannings.tasks.uncomplete', [$planning, $planningTask]) }}" class="p-6">
                                                                            @csrf

                                                                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                                                {{ __('Taak Heropenen: ') . $planningTask->title }}
                                                                            </h2>

                                                                            <div class="mt-6">
                                                                                <x-input-label for="rejection_reason_{{ $planningTask->id }}" value="{{ __('Reden voor heropenen') }}" />
                                                                                <textarea name="rejection_reason" id="rejection_reason_{{ $planningTask->id }}" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>{{ old('rejection_reason') }}</textarea>
                                                                                <x-input-error class="mt-2" :messages="$errors->get('rejection_reason')" />
                                                                            </div>

                                                                            <div class="mt-6 flex justify-end">
                                                                                <x-secondary-button x-on:click="$dispatch('close')">
                                                                                    {{ __('Annuleren') }}
                                                                                </x-secondary-button>

                                                                                <x-primary-button class="ml-3">
                                                                                    {{ __('Taak heropenen') }}
                                                                                </x-primary-button>
                                                                            </div>
                                                                        </form>
                                                                    </x-modal>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                    <tr>
                                                        <td colspan="2" class="px-4 py-3 text-sm font-semibold text-right text-gray-700 dark:text-gray-200">Totaal geschatte tijd voor {{ $location->name }}:</td>
                                                        <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                                            @php
                                                                $hours = floor($totalMinutesForLocation / 60);
                                                                $minutes = $totalMinutesForLocation % 60;
                                                            @endphp
                                                            {{ $hours > 0 ? $hours . ' uur ' : '' }}{{ $minutes > 0 ? $minutes . ' min' : ($hours == 0 ? '0 min' : '') }}
                                                            {{ $totalMinutesForLocation == 0 && $hours == 0 ? 'N/A' : '' }}
                                                        </td>
                                                        <td colspan="2" class="px-4 py-3 text-sm"></td> {{-- Empty cells for remaining columns --}}
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

        </div>
    </div>
    <x-modal-image />


</x-app-layout>
