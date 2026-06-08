<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📍 {{ __('Locatie Overzicht') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap gap-3">
                <span class="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                    Gem. bezoeken 30d: {{ number_format($averages['avg_visits_30d'], 1, ',', '.') }}
                </span>
                <span class="inline-flex items-center px-3 py-1 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-full dark:bg-indigo-900/30 dark:text-indigo-300">
                    Gem. bezoeken 365d: {{ number_format($averages['avg_visits_365d'], 1, ',', '.') }}
                </span>
                <span class="inline-flex items-center px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-gray-300">
                    {{ $locations->total() }} locaties
                </span>
            </div>

            <form action="{{ route('admin.locations.report') }}" method="GET" id="locationReportSearchForm" class="mb-6">
                @php
                    $perPage = (string) request('per_page', '15');
                @endphp
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="relative flex items-center flex-1 max-w-md">
                        <span class="absolute">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="search_term"
                            id="locationReportSearchInput"
                            value="{{ $searchTerm }}"
                            placeholder="Zoek locatie..."
                            class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg placeholder-gray-400/70 pl-11 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40"
                        >
                        <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                        <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                    </div>

                    <div class="flex items-center gap-x-2">
                        <label for="report-per-page" class="text-xs text-gray-500 dark:text-gray-300">Items per pagina</label>
                        <select id="report-per-page" name="per_page" class="py-1.5 pl-2 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40" onchange="this.form.submit()">
                            <option value="15" {{ $perPage === '15' ? 'selected' : '' }}>15</option>
                            <option value="30" {{ $perPage === '30' ? 'selected' : '' }}>30</option>
                            <option value="50" {{ $perPage === '50' ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage === '100' ? 'selected' : '' }}>100</option>
                            <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>Alles</option>
                        </select>
                    </div>
                </div>
            </form>

            @if ($locations->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                        Geen locaties gevonden voor de huidige selectie.
                    </div>
                </div>
            @else
                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg bg-white dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    @php
                                        $sortParams = ['search_term' => $searchTerm, 'per_page' => request('per_page')];
                                        $columns = [
                                            'name' => 'Locatie',
                                            'last_visit_at' => 'Laatste bezoek',
                                            'last_controleronde_at' => 'Laatste controleronde',
                                            'last_schoonmaak_at' => 'Laatste schoonmaak',
                                            'visits_30d' => 'Bezoeken 30d',
                                            'visits_365d' => 'Bezoeken 365d',
                                        ];
                                    @endphp
                                    @foreach ($columns as $column => $label)
                                        <th scope="col" class="px-4 py-3.5 text-sm font-normal {{ $column === 'name' ? 'text-left' : 'text-center' }} text-gray-500 dark:text-gray-400">
                                            <a href="{{ route('admin.locations.report', array_merge($sortParams, [
                                                'sort_by' => $column,
                                                'sort_direction' => ($sortBy === $column && $sortDirection === 'asc') ? 'desc' : 'asc',
                                            ])) }}" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                                <span>{{ $label }}</span>
                                                @if ($sortBy === $column)
                                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-center text-gray-500 dark:text-gray-400">
                                        Trend (12 mnd)
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($locations as $location)
                                    @php
                                        $locationTrend = $trendData[$location->id] ?? [];
                                        $maxTrend = max(1, ...array_values($locationTrend ?: [0]));
                                    @endphp
                                    <tr class="{{ $loop->odd ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800' }}">
                                        <td class="px-4 py-4 text-sm font-medium text-gray-800 dark:text-white whitespace-nowrap">
                                            {{ $location->name }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            @if ($location->last_visit_at)
                                                <div>{{ \Carbon\Carbon::parse($location->last_visit_at)->format('d-m-Y') }}</div>
                                                <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($location->last_visit_at)->diffForHumans() }}</div>
                                            @else
                                                <span class="text-gray-400">Nooit</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            @if ($location->last_controleronde_at)
                                                <div>{{ \Carbon\Carbon::parse($location->last_controleronde_at)->format('d-m-Y') }}</div>
                                                <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($location->last_controleronde_at)->diffForHumans() }}</div>
                                            @else
                                                <span class="text-gray-400">Nooit</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            @if ($location->last_schoonmaak_at)
                                                <div>{{ \Carbon\Carbon::parse($location->last_schoonmaak_at)->format('d-m-Y') }}</div>
                                                <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($location->last_schoonmaak_at)->diffForHumans() }}</div>
                                            @else
                                                <span class="text-gray-400">Nooit</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @php
                                                $visits30Class = match (true) {
                                                    $location->visits_30d > $averages['avg_visits_30d'] => 'text-green-700 bg-green-100 dark:text-green-300 dark:bg-green-900/30',
                                                    $location->visits_30d < $averages['avg_visits_30d'] => 'text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/30',
                                                    default => 'text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 text-sm font-medium rounded-full {{ $visits30Class }}">
                                                {{ $location->visits_30d }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @php
                                                $visits365Class = match (true) {
                                                    $location->visits_365d > $averages['avg_visits_365d'] => 'text-green-700 bg-green-100 dark:text-green-300 dark:bg-green-900/30',
                                                    $location->visits_365d < $averages['avg_visits_365d'] => 'text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/30',
                                                    default => 'text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 text-sm font-medium rounded-full {{ $visits365Class }}">
                                                {{ $location->visits_365d }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-end justify-center gap-0.5 h-8" title="Bezoeken per maand (12 maanden)">
                                                @foreach ($locationTrend as $month => $count)
                                                    @php
                                                        $height = max(2, (int) round(($count / $maxTrend) * 100));
                                                    @endphp
                                                    <span
                                                        class="w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80"
                                                        style="height: {{ $height }}%"
                                                        title="{{ $month }}: {{ $count }}"
                                                    ></span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6">
                    {{ $locations->links() }}
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('locationReportSearchInput');
                const searchForm = document.getElementById('locationReportSearchForm');
                if (!searchInput || !searchForm) {
                    return;
                }

                let debounceTimer;
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function () {
                        searchForm.submit();
                    }, 500);
                });
            });
        </script>
    @endpush
</x-app-layout>
