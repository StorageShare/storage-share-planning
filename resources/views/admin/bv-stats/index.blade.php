<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📊 {{ __('BV Statistieken') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Date Filter --}}
            <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('admin.bv-stats.index') }}" class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="from_date" :value="__('Van datum')" />
                            <x-text-input id="from_date" name="from_date" type="date" 
                                          value="{{ $fromDate }}" class="mt-1 block w-full" />
                        </div>
                        
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="to_date" :value="__('Tot datum')" />
                            <x-text-input id="to_date" name="to_date" type="date" 
                                          value="{{ $toDate }}" class="mt-1 block w-full" />
                        </div>
                        
                        <div>
                            <x-primary-button type="submit">
                                {{ __('Filter') }}
                            </x-primary-button>
                        </div>
                        
                        <div>
                            <x-secondary-button type="button" onclick="setCurrentMonth()">
                                {{ __('Huidige maand') }}
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Date range info --}}
            <div class="mb-6">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-blue-800 dark:text-blue-200">
                            Periode: {{ $fromCarbon->format('d-m-Y') }} tot {{ $toCarbon->format('d-m-Y') }}
                            ({{ $fromCarbon->diffInDays($toCarbon) + 1 }} dagen)
                        </span>
                    </div>
                </div>
            </div>

            {{-- No data message --}}
            @if(empty($bvStats))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Geen BV informatie gevonden</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Er zijn geen locaties met BV informatie gevonden. BV statistieken kunnen alleen worden getoond voor locaties waarbij het BV veld is ingevuld.
                        </p>
                        <div class="flex justify-center">
                            <a href="{{ route('locations.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Locaties beheren
                            </a>
                        </div>
                    </div>
                </div>
            @elseif(!collect($bvStats)->sum(fn($bv) => $bv['total_work_seconds'] + $bv['total_travel_seconds']))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Geen tijdregistraties gevonden</h3>
                        <p class="text-gray-600 dark:text-gray-400">Er zijn geen tijdregistraties gevonden voor de geselecteerde periode.</p>
                    </div>
                </div>
            @else
                {{-- Summary cards --}}
                @php
                    $totalWorkSeconds = collect($bvStats)->sum('total_work_seconds');
                    $totalTravelSeconds = collect($bvStats)->sum('total_travel_seconds');
                    $totalSeconds = $totalWorkSeconds + $totalTravelSeconds;
                    $totalBvs = count($bvStats);
                @endphp
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Totale uren</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $totalSeconds >= 3600 ? number_format($totalSeconds / 3600, 1) . 'u' : number_format($totalSeconds / 60) . 'm' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Gewerkte uren</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $totalWorkSeconds >= 3600 ? number_format($totalWorkSeconds / 3600, 1) . 'u' : number_format($totalWorkSeconds / 60) . 'm' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Reisuren</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $totalTravelSeconds >= 3600 ? number_format($totalTravelSeconds / 3600, 1) . 'u' : number_format($totalTravelSeconds / 60) . 'm' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">BVs</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $totalBvs }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- BV Statistics Table --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">BV Overzicht</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Daadwerkelijk gewerkte tijd per bedrijf (tijd × aantal medewerkers) inclusief reistijd verdeling</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        BV
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Gewerkte uren
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Reisuren
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Totaal uren
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Locaties
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Planningen
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($bvStats as $bv => $stats)
                                    @php
                                        $totalBvSeconds = $stats['total_work_seconds'] + $stats['total_travel_seconds'];
                                        $workHours = floor($stats['total_work_seconds'] / 3600);
                                        $workMinutes = floor(($stats['total_work_seconds'] % 3600) / 60);
                                        $travelHours = floor($stats['total_travel_seconds'] / 3600);
                                        $travelMinutes = floor(($stats['total_travel_seconds'] % 3600) / 60);
                                        $totalHours = floor($totalBvSeconds / 3600);
                                        $totalMinutes = floor(($totalBvSeconds % 3600) / 60);
                                        $percentage = $totalSeconds > 0 ? ($totalBvSeconds / $totalSeconds) * 100 : 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bv }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($percentage, 1) }}% van totaal</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                                @if($stats['total_work_seconds'] > 0)
                                                    {{ $workHours > 0 ? $workHours . 'u ' : '' }}{{ $workMinutes }}m
                                                @else
                                                    <span class="text-gray-400">0m</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                                @if($stats['total_travel_seconds'] > 0)
                                                    {{ $travelHours > 0 ? $travelHours . 'u ' : '' }}{{ $travelMinutes }}m
                                                @else
                                                    <span class="text-gray-400">0m</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $totalHours > 0 ? $totalHours . 'u ' : '' }}{{ $totalMinutes }}m
                                            </div>
                                            @if($totalSeconds > 0)
                                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-1">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ count($stats['locations']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $stats['planning_count'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button 
                                                type="button" 
                                                onclick="toggleDetails('{{ $bv }}')"
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium"
                                            >
                                                Bekijk details
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    {{-- Expandable details row --}}
                                    <tr id="details-{{ $bv }}" class="hidden bg-gray-50 dark:bg-gray-900">
                                        <td colspan="7" class="px-6 py-4">
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Locatie details voor {{ $bv }}</h4>
                                                @if(count($stats['locations']) > 0)
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                        @foreach($stats['locations'] as $locationId => $location)
                                                            @php
                                                                $locationHours = floor($location['total_seconds'] / 3600);
                                                                $locationMinutes = floor(($location['total_seconds'] % 3600) / 60);
                                                            @endphp
                                                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $location['name'] }}</div>
                                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                    {{ $locationHours > 0 ? $locationHours . 'u ' : '' }}{{ $locationMinutes }}m
                                                                    ({{ $location['visit_count'] }} bezoek{{ $location['visit_count'] !== 1 ? 'en' : '' }})
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-gray-500 dark:text-gray-400">Geen locatie details beschikbaar</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function setCurrentMonth() {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth(); // 0-based (0 = January)
            
            // Create dates in local timezone to avoid timezone conversion issues
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0); // Last day of current month
            
            // Format dates as YYYY-MM-DD in local timezone
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            document.getElementById('from_date').value = formatDate(firstDay);
            document.getElementById('to_date').value = formatDate(lastDay);
        }
        
        function toggleDetails(bv) {
            const detailsRow = document.getElementById('details-' + bv);
            if (detailsRow.classList.contains('hidden')) {
                detailsRow.classList.remove('hidden');
            } else {
                detailsRow.classList.add('hidden');
            }
        }
    </script>
</x-app-layout> 