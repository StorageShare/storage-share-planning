<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Timer Overzicht') }}
        </h2>
    </x-slot>
<div class="py-12">
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">

                {{-- Header met filters --}}
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h3 class="text-lg font-semibold">Timer Gegevens</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Overzicht van alle gewerkte tijden per planning</p>
                        </div>

                        {{-- Export knop --}}
                        <div class="flex space-x-2">
                            <form action="{{ route('admin.timers.export') }}" method="GET" class="inline">
                                @foreach(request()->all() as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Exporteer CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Filters --}}
                <form method="GET" action="{{ route('admin.timers.index') }}" class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Van datum</label>
                            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                        </div>

                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tot datum</label>
                            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                        </div>

                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gebruiker</label>
                            <select name="user_id" id="user_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                <option value="">Alle gebruikers</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                Filter
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Plannings lijst --}}
                @if($plannings->count() > 0)
                    <div class="space-y-4">
                        @foreach($plannings as $planning)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 dark:bg-gray-700 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-lg font-semibold">Planning #{{ $planning->id }}</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $planning->planned_date->format('d-m-Y') }} -
                                                {{ $planning->users->pluck('name')->join(', ') ?: 'Geen gebruikers toegewezen' }}
                                            </p>
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            @php
                                                $totalSeconds = $planning->locationTimers->sum('total_duration_seconds');
                                                $totalHours = floor($totalSeconds / 3600);
                                                $totalMinutes = floor(($totalSeconds % 3600) / 60);
                                                $hasActiveTimers = $planning->locationTimers->where('started_at', '!=', null)->where('ended_at', null)->count() > 0;
                                            @endphp

                                            @if($hasActiveTimers)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                                                    Actief
                                                </span>
                                            @endif

                                            <div class="text-right">
                                                <div class="text-lg font-mono font-bold">
                                                    {{ sprintf('%02d:%02d', $totalHours, $totalMinutes) }}
                                                </div>
                                                <div class="text-xs text-gray-500">Totale tijd</div>
                                            </div>

                                            <a href="{{ route('admin.timers.show', $planning) }}"
                                                class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                                Details
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Timer samenvatting --}}
                                @if($planning->locationTimers->count() > 0)
                                    <div class="p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                            @foreach($planning->locationTimers as $timer)
                                                @php
                                                    $timerSeconds = 0;
                                                    if ($timer->started_at && !$timer->ended_at) {
                                                        $timerSeconds = ($timer->total_duration_seconds ?? 0) + $timer->started_at->diffInSeconds(now());
                                                    } else {
                                                        $timerSeconds = $timer->total_duration_seconds ?? 0;
                                                    }

                                                    $hours = floor($timerSeconds / 3600);
                                                    $minutes = floor(($timerSeconds % 3600) / 60);
                                                    $locationName = $timer->location ? $timer->location->name :
                                                        ($timer->label());
                                                @endphp

                                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                                {{ $locationName }}
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $timer->label() }}
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-sm font-mono font-bold">
                                                                {{ sprintf('%02d:%02d', $hours, $minutes) }}
                                                            </div>
                                                            @if($timer->started_at && !$timer->ended_at)
                                                                <div class="text-xs text-green-600">Actief</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                                        Geen timer gegevens beschikbaar
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Paginering --}}
                    <div class="mt-6">
                        {{ $plannings->withQueryString()->links() }}
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Geen timer gegevens</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Er zijn geen timer gegevens gevonden voor de geselecteerde filters.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-app-layout>
