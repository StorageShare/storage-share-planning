<x-app-layout>
    <x-slot name="header">
        {{-- The header content will be managed by the section below --}}
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Planningen</h2>
                            @if(!$plannings->isEmpty())
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $plannings->total() }} planningen</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle gemaakte planningen.</p>
                    </div>

                    @anyrole('admin', 'facilities_coordinator')
                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('plannings.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Planning</span>
                        </a>
                    </div>
                    @endanyrole
                </div>

                <form action="{{ route('plannings.index') }}" method="GET" id="planningSearchForm">
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                        <div class="inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            @php
                                $filterBaseParams = array_filter([
                                    'search_term' => $searchTerm,
                                    'filter' => $activeFilter,
                                    'awaiting_approval' => ($awaitingApproval ?? false) ? '1' : null,
                                ]);
                            @endphp
                            <a href="{{ route('plannings.index', $filterBaseParams) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !$activeFilter ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Bekijk alle
                            </a>
                            <a href="{{ route('plannings.index', array_merge($filterBaseParams, ['filter' => 'open'])) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'open' ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Open
                            </a>
                            <a href="{{ route('plannings.index', array_merge($filterBaseParams, ['filter' => 'completed'])) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'completed' ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Voltooid
                            </a>
                        </div>

                        @php
                            $awaitParamsBase = array_filter(request()->except('page'));
                            $awaitOnParams = array_merge($awaitParamsBase, ['awaiting_approval' => '1']);
                            $awaitOffParams = $awaitParamsBase;
                            unset($awaitOffParams['awaiting_approval']);
                        @endphp
                        <div class="inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700 mt-4 md:mt-0">
                            <a href="{{ route('plannings.index', $awaitOnParams) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ ($awaitingApproval ?? false) ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Met taken ter beoordeling
                            </a>
                            <a href="{{ route('plannings.index', $awaitOffParams) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !($awaitingApproval ?? false) ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Alle planningen
                            </a>
                        </div>

                        <div class="flex items-center mt-4 md:mt-0 gap-x-2">
                            <div class="relative flex items-center">
                                <span class="absolute">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </span>
                                <input type="text" name="search_term" id="planningSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek in notities, locaties..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">
                            </div>

                            <div class="relative flex items-center">
                                <input type="date" name="planned_date" id="planningDateInput" value="{{ request('planned_date') }}" class="block w-full py-1.5 px-3 text-gray-700 bg-white border border-gray-200 rounded-lg placeholder-gray-400/70 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">
                            </div>

                            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                            @if($activeFilter)
                                <input type="hidden" name="filter" value="{{ $activeFilter }}">
                            @endif
                            @if($awaitingApproval ?? false)
                                <input type="hidden" name="awaiting_approval" value="1">
                            @endif
                        </div>
                    </div>
                </form>

                <div class="flex flex-col mt-6">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                            <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            @php
                                                $routeParams = array_filter([
                                                    'search_term' => $searchTerm,
                                                    'filter' => $activeFilter,
                                                    'awaiting_approval' => ($awaitingApproval ?? false) ? '1' : null,
                                                ]);
                                            @endphp
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('plannings.index', array_merge($routeParams, ['sort_by' => 'planned_date', 'sort_direction' => ($sortBy == 'planned_date' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Geplande datum</span>
                                                    @if ($sortBy == 'planned_date') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Toegekende gebruikers</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Locatie(s)</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('plannings.index', array_merge($routeParams, ['sort_by' => 'status', 'sort_direction' => ($sortBy == 'status' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Status</span>
                                                    @if ($sortBy == 'status') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('plannings.index', array_merge($routeParams, ['sort_by' => 'planning_tasks_count', 'sort_direction' => ($sortBy == 'planning_tasks_count' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Taken</span>
                                                    @if ($sortBy == 'planning_tasks_count') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="relative py-3.5 px-4">
                                                <span class="sr-only">Acties</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($plannings as $planning)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $planning->planned_date->format('d-m-Y') }}</td>
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap text-gray-700 dark:text-gray-200">
                                                    {{ $planning->users->isNotEmpty() ? $planning->users->pluck('name')->join(', ') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    @if($planning->locations->isNotEmpty())
                                                        <div class="space-y-1">
                                                            @foreach($planning->locations as $location)
                                                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                                                    {{ $location->name }}
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                        @switch(strtolower($planning->status ?? ''))
                                                            @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                            @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                            @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                            @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                                        @endswitch
                                                    ">
                                                        {{ ucfirst(str_replace('_', ' ', $planning->status ?? 'Onbekend')) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-center text-gray-700 dark:text-gray-200">{{ $planning->planning_tasks_count }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('plannings.show', $planning) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a>
                                                    @can('update', $planning)
                                                        <a href="{{ route('plannings.edit', $planning) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                        @if($planning->users->isNotEmpty())
                                                            <form action="{{ route('plannings.send-notifications', $planning) }}" method="POST" class="inline-block">
                                                                @csrf
                                                                <button type="submit"
                                                                        class="px-2 py-1 text-xs text-green-600 transition-colors duration-200 rounded-md hover:bg-green-100 dark:hover:bg-gray-800 dark:text-green-400"
                                                                        onclick="return confirm('Weet je zeker dat je alle gebruikers van deze planning wilt notificeren?')"
                                                                        title="Stuur notificaties naar gebruikers">
                                                                    📧 Notify
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('delete', $planning)
                                                        <form action="{{ route('plannings.destroy', $planning) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze planning wilt verwijderen?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="px-2 py-1 text-xs text-red-600 transition-colors duration-200 rounded-md hover:bg-red-100 dark:hover:bg-gray-800 dark:text-red-400">Verwijderen</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                                    @if (!empty($searchTerm) || !empty($activeFilter) || !empty(request('planned_date')))
                                                        <p>Geen planningen gevonden voor de huidige selectie.</p>
                                                        <div class="mt-2">
                                                            <a href="{{ route('plannings.index') }}" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis filters en zoekopdracht</a>
                                                        </div>
                                                    @else
                                                        <p>Er zijn geen planningen beschikbaar.</p>
                                                        @anyrole('admin', 'facilities_coordinator')
                                                        <div class="mt-4">
                                                            <a href="{{ route('plannings.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 mx-auto text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                <span>Nieuwe Planning</span>
                                                            </a>
                                                        </div>
                                                        @endanyrole
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                @if($plannings->hasPages())
                <div class="mt-6 sm:flex sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $plannings->currentPage() }} van {{ $plannings->lastPage() }}</span>
                    </div>
                    <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                        {{ $plannings->appends(request()->query())->links('vendor.pagination.tailwind') }}
                    </div>
                </div>
                @endif
            </section>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('planningSearchInput');
        const dateInput = document.getElementById('planningDateInput');
        const searchForm = document.getElementById('planningSearchForm');
        const searchTermFromServer = "{{ $searchTerm ?? '' }}";
        let debounceTimer;

        // Function to set focus and cursor at the end of the input
        function focusAndSetCursor(inputElement) {
            const val = inputElement.value;
            inputElement.focus();
            inputElement.value = '';
            inputElement.value = val;
        }

        if (searchInput && searchForm) {
            // If the form was submitted via JS (e.g. search), restore focus
            if (sessionStorage.getItem('planningSearchSubmitted') === 'true') {
                focusAndSetCursor(searchInput);
                sessionStorage.removeItem('planningSearchSubmitted');
            } else if (searchTermFromServer && searchTermFromServer.length > 0 && document.activeElement !== searchInput) {
                // If search term came from server (e.g. pagination click) and input is not already focused
                focusAndSetCursor(searchInput);
            }

            searchInput.addEventListener('input', function () {
                // No need to manually add sort_by, sort_direction, filter here
                // They are already part of the form as hidden inputs or will be added by filter links
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    sessionStorage.setItem('planningSearchSubmitted', 'true');
                    searchForm.submit();
                }, 500);
            });
        }

        if (dateInput && searchForm) {
            dateInput.addEventListener('change', function() {
                searchForm.submit();
            });
        }
    });
</script>

</x-app-layout>
