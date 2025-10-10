<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Timer Details - Planning #' . $planning->id) }}
        </h2>
    </x-slot>
<div class="py-12" x-data="timerDetails()" x-init="init()">
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">

        {{-- Planning info --}}
        <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold">Planning #{{ $planning->id }}</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Datum: {{ $planning->planned_date->format('d-m-Y') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-500">
                            Gebruikers: {{ $planning->users->pluck('name')->join(', ') ?: 'Geen gebruikers toegewezen' }}
                        </p>
                    </div>

                    <div class="mt-4 md:mt-0 text-right">
                        @php
                            $totalHours = floor($totalDurationSeconds / 3600);
                            $totalMinutes = floor(($totalDurationSeconds % 3600) / 60);
                            $totalSeconds = $totalDurationSeconds % 60;
                        @endphp
                        <div class="text-3xl font-mono font-bold text-blue-600 dark:text-blue-400" x-text="totalFormattedTime">
                            {{ sprintf('%02d:%02d:%02d', $totalHours, $totalMinutes, $totalSeconds) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Totale gewerkte tijd</div>
                    </div>
                </div>

                <div class="mt-4 flex space-x-2">
                    <a href="{{ route('admin.timers.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        ← Terug naar overzicht
                    </a>

                    <a href="{{ route('plannings.show', $planning) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Bekijk planning
                    </a>
                </div>
            </div>
        </div>

        {{-- Timer details --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h4 class="text-lg font-semibold mb-4">Timer Details per Locatie</h4>

                @if($timersByLocation->count() > 0)
                    <div class="space-y-4">
                        @foreach($timersByLocation as $index => $timerData)
                            @php $timer = $timerData['timer']; @endphp
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        @if($timerData['timer']->location_type === 'shared_travel')
                                            <div class="w-8 h-8 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs font-medium mr-4">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                </svg>
                                            </div>
                                        @elseif($timerData['timer']->location_type === 'location')
                                            <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-medium mr-4">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                        @elseif($timerData['timer']->location_type === 'travel')
                                            <div class="w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center text-xs font-medium mr-4">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                </svg>
                                            </div>
                                       @else
                                            <div class="w-8 h-8 bg-gray-500 text-white rounded-full flex items-center justify-center text-xs font-medium mr-4">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                                </svg>
                                            </div>
                                        @endif

                                        <div>
                                            <h5 class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $timerData['location_name'] }}
                                            </h5>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ ucfirst($timer->location_type) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-4">
                                        @if($timerData['is_active'])
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                                                Actief
                                            </span>
                                        @endif

                                        <div class="text-right">
                                            <div class="text-2xl font-mono font-bold text-blue-600 dark:text-blue-400"
                                                 x-text="timerDisplays[{{ $index }}] || '{{ $timerData['formatted_duration'] }}'">
                                                {{ $timerData['formatted_duration'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                @if($timerData['is_active'])
                                                    Lopende tijd
                                                @else
                                                    Totale tijd
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Timer details --}}
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Gestart:</span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ $timer->created_at ? $timer->created_at->format('d-m-Y H:i:s') : 'Nog niet gestart' }}
                                        </span>
                                    </div>

                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Gestopt:</span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ $timer->ended_at ? $timer->ended_at->format('d-m-Y H:i:s') : ($timer->started_at ? 'Nog actief' : 'Nog niet gestart') }}
                                        </span>
                                    </div>

                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            @if($timer->started_at && !$timer->ended_at)
                                                <span class="text-green-600">Actief</span>
                                            @elseif($timer->ended_at)
                                                <span class="text-blue-600">Voltooid</span>
                                            @else
                                                <span class="text-gray-600">Nog niet gestart</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Geen timer gegevens</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Er zijn nog geen timer gegevens beschikbaar voor deze planning.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

</div>

@push('scripts')
<script>
function timerDetails() {
    return {
        planningId: {{ $planning->id }},
        timerDisplays: {},
        totalFormattedTime: '',
        refreshInterval: null,

        init() {
            this.startLiveUpdates();
        },

        startLiveUpdates() {
            this.updateTimers();
            this.refreshInterval = setInterval(() => {
                this.updateTimers();
            }, 1000);

            // Clean up interval when page is unloaded
            window.addEventListener('beforeunload', () => {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
            });
        },

        async updateTimers() {
            try {
                const response = await fetch(`/admin/timers/${this.planningId}/live-data`);
                const timers = await response.json();

                let totalSeconds = 0;

                timers.forEach((timer, index) => {
                    if (timer.is_active && timer.started_at) {
                        // Calculate current time for active timers
                        const startTime = new Date(timer.started_at).getTime();
                        const currentTime = Date.now();
                        const elapsedSeconds = Math.floor((currentTime - startTime) / 1000);
                        const currentTotal = (timer.total_seconds || 0) + elapsedSeconds;

                        const hours = Math.floor(currentTotal / 3600);
                        const minutes = Math.floor((currentTotal % 3600) / 60);
                        const seconds = currentTotal % 60;

                        this.timerDisplays[index] = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                        totalSeconds += currentTotal;
                    } else {
                        // Use static time for inactive timers
                        this.timerDisplays[index] = timer.formatted_duration;
                        totalSeconds += timer.total_seconds || 0;
                    }
                });

                // Update total time
                const totalHours = Math.floor(totalSeconds / 3600);
                const totalMinutes = Math.floor((totalSeconds % 3600) / 60);
                const totalSecondsRemainder = totalSeconds % 60;
                this.totalFormattedTime = `${String(totalHours).padStart(2, '0')}:${String(totalMinutes).padStart(2, '0')}:${String(totalSecondsRemainder).padStart(2, '0')}`;

            } catch (error) {
                console.error('Error fetching timer data:', error);
            }
        }
    }
}
</script>
@endpush
</x-app-layout>
