<x-app-layout>
    <x-slot name="header">
        {{-- Header content is now managed by the new section below --}}
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">

            <div class="mb-4">
                <a href="{{ route('locations.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">&larr; Terug naar locaties</a>
            </div>

            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Taken voor {{ $location->name }}</h2>
                            @if(!$tasks->isEmpty())
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $tasks->total() }} taken</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle taken voor deze locatie.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('locations.tasks.create', $location) }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Taak</span>
                        </a>
                    </div>
                </div>

                <form action="{{ route('locations.tasks.index', $location) }}" method="GET" id="taskSearchForm">
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                        <div class="inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !$activeFilter ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Bekijk alle
                            </a>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'filter' => 'open', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'open' ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Open
                            </a>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'filter' => 'completed', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'completed' ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Voltooid
                            </a>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'filter' => 'priority_high', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'priority_high' ? 'bg-red-100 dark:bg-red-800' : 'hover:bg-red-100 dark:hover:bg-red-800' }}">
                                Prio Hoog
                            </a>
                        </div>

                        {{-- New Planned Status Filters --}}
                        <div class="mt-2 inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            <span class="px-5 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 sm:text-sm">Gepland:</span>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection, 'filter' => $activeFilter])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !$plannedFilter ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Alle
                            </a>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'planned_filter' => 'planned', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection, 'filter' => $activeFilter])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $plannedFilter === 'planned' ? 'bg-green-100 dark:bg-green-800' : 'hover:bg-green-100 dark:hover:bg-green-800' }}">
                                Gepland
                            </a>
                            <a href="{{ route('locations.tasks.index', array_filter(['location' => $location->id, 'planned_filter' => 'unplanned', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection, 'filter' => $activeFilter])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $plannedFilter === 'unplanned' ? 'bg-yellow-100 dark:bg-yellow-800' : 'hover:bg-yellow-100 dark:hover:bg-yellow-800' }}">
                                Ongepland
                            </a>
                        </div>

                        <div class="relative flex items-center mt-4 md:mt-0">
                            <span class="absolute">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <input type="text" name="search_term" id="taskSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek taken..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">

                            @if(request()->has('sort_by'))
                            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                            @endif
                            @if(request()->has('sort_direction'))
                            <input type="hidden" name="sort_direction" value="{{ request('sort_direction') }}">
                            @endif
                            @if(request()->has('filter'))
                            <input type="hidden" name="filter" value="{{ request('filter') }}">
                            @endif
                            @if(request()->has('planned_filter'))
                            <input type="hidden" name="planned_filter" value="{{ request('planned_filter') }}">
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
                                            $routeParams = ['location' => $location->id, 'search_term' => $searchTerm, 'filter' => $activeFilter, 'planned_filter' => $plannedFilter];
                                            $textSort = ($sortBy == 'COLUMN_NAME' && $sortDirection == 'asc') ? 'desc' : 'asc';
                                            $numericDateSort = ($sortBy == 'COLUMN_NAME') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc';
                                            @endphp
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'title', 'sort_direction' => ($sortBy == 'title' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Titel</span>
                                                    @if ($sortBy == 'title') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'priority', 'sort_direction' => ($sortBy == 'priority') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Prioriteit</span>
                                                    @if ($sortBy == 'priority') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'status', 'sort_direction' => ($sortBy == 'status' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Status</span>
                                                    @if ($sortBy == 'status') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'deadline', 'sort_direction' => ($sortBy == 'deadline') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Deadline</span>
                                                    @if ($sortBy == 'deadline') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'estimated_time_minutes', 'sort_direction' => ($sortBy == 'estimated_time_minutes') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Geschatte tijd (minuten)</span>
                                                    @if ($sortBy == 'estimated_time_minutes') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('locations.tasks.index', array_merge($routeParams, ['sort_by' => 'created_at', 'sort_direction' => ($sortBy == 'created_at') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                    <span>Aangemaakt</span>
                                                    @if ($sortBy == 'created_at') <x-sort-icon :direction="$sortDirection" /> @endif
                                                </a>
                                            </th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                <span>Planning</span>
                                            </th>
                                            <th scope="col" class="relative py-3.5 px-4">
                                                <span class="sr-only">Acties</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($tasks as $task)
                                        <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                            <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                <div>
                                                    <h2 class="font-medium text-gray-800 dark:text-white ">{{ $task->title }}</h2>
                                                    <p class="text-sm font-normal text-gray-600 dark:text-gray-400">{{ Str::limit($task->description, 40) }}</p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    @switch($task->priority->value)
                                                        @case(App\Enums\TaskPriority::HIGH->value) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                        @case(App\Enums\TaskPriority::NORMAL->value) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                        @case(App\Enums\TaskPriority::LOW->value) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                                    @endswitch
                                                ">
                                                    {{ $task->priority->label() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    @switch($task->status->value)
                                                        @case('concept') bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200 @break
                                                        @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                        @case('in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                        @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                        @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                                    @endswitch
                                                ">
                                                    {{ ucfirst(str_replace('_', ' ', $task->status->value)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->deadline ? $task->deadline->format('d-m-Y') : '-' }}</td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->estimated_time_minutes ?? '-' }}</td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->created_at->format('d-m-Y H:i') }}</td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                @if($task->planningTasks->isNotEmpty())
                                                    @foreach($task->planningTasks as $planningTask)
                                                        @if($planningTask->planning)
                                                            <a href="{{ route('plannings.show', $planningTask->planning) }}"
                                                               class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-100 rounded-full hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition-colors duration-200">
                                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                                </svg>
                                                                {{ $planningTask->planning->planned_date->format('d-m-Y') }}
                                                            </a>
                                                            @if(!$loop->last)
                                                                <br class="mb-1">
                                                            @endif
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-400">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Ongepland
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                <a href="{{ route('tasks.show', $task) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a>
                                                <a href="{{ route('tasks.edit', $task) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                @anyrole('admin', 'facilities_coordinator')
                                                <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-2 py-1 text-xs text-red-600 transition-colors duration-200 rounded-md hover:bg-red-100 dark:hover:bg-gray-800 dark:text-red-400">Verwijderen</button>
                                                </form>
                                                @endanyrole
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                                @if (!empty($searchTerm) || !empty($activeFilter) || !empty($plannedFilter))
                                                <p>Geen taken gevonden voor de huidige selectie.</p>
                                                <div class="mt-2">
                                                    <a href="{{ route('locations.tasks.index', $location) }}" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis filters en zoekopdracht</a>
                                                </div>
                                                @else
                                                <p>Er zijn nog geen taken aangemaakt voor locatie "{{ $location->name }}".</p>
                                                <div class="mt-4">
                                                    <a href="{{ route('locations.tasks.create', $location) }}" class="flex items-center justify-center w-1/2 px-5 py-2 mx-auto text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span>Nieuwe Taak voor {{ $location->name }}</span>
                                                    </a>
                                                </div>
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

                @if($tasks->hasPages())
                <div class="mt-6 sm:flex sm:items-center sm:justify-between ">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $tasks->currentPage() }} van {{ $tasks->lastPage() }}</span>
                    </div>
                    <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                        {{ $tasks->links('vendor.pagination.tailwind') }}
                    </div>
                </div>
                @endif
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('taskSearchInput');
            const searchForm = document.getElementById('taskSearchForm');
            const searchTermFromServer = "@json($searchTerm ?? '')";
            let debounceTimer;

            if (searchInput && searchForm) {
                if (sessionStorage.getItem('taskSearchSubmitted') === 'true') {
                    searchInput.focus();
                    const val = searchInput.value;
                    searchInput.value = '';
                    searchInput.value = val;
                    sessionStorage.removeItem('taskSearchSubmitted');
                } else if (searchTermFromServer && searchTermFromServer.length > 0) {
                    searchInput.focus();
                    const val = searchInput.value;
                    searchInput.value = '';
                    searchInput.value = val;
                }

                searchInput.addEventListener('input', function() {
                    const currentSortBy = "@json($sortBy ?? '')";
                    const currentSortDirection = "@json($sortDirection ?? '')";
                    const currentFilter = "@json($activeFilter ?? '')";
                    const currentPlannedFilter = "@json($plannedFilter ?? '')";

                    let sortByInput = searchForm.querySelector('input[name="sort_by"]');
                    if (!sortByInput) {
                        sortByInput = document.createElement('input');
                        sortByInput.type = 'hidden';
                        sortByInput.name = 'sort_by';
                        searchForm.appendChild(sortByInput);
                    }
                    sortByInput.value = currentSortBy;

                    let sortDirectionInput = searchForm.querySelector('input[name="sort_direction"]');
                    if (!sortDirectionInput) {
                        sortDirectionInput = document.createElement('input');
                        sortDirectionInput.type = 'hidden';
                        sortDirectionInput.name = 'sort_direction';
                        searchForm.appendChild(sortDirectionInput);
                    }
                    sortDirectionInput.value = currentSortDirection;

                    let filterInput = searchForm.querySelector('input[name="filter"]');
                    if (currentFilter) {
                        if (!filterInput) {
                            filterInput = document.createElement('input');
                            filterInput.type = 'hidden';
                            filterInput.name = 'filter';
                            searchForm.appendChild(filterInput);
                        }
                        filterInput.value = currentFilter;
                    } else if (filterInput) {
                        filterInput.remove(); // Remove if currentFilter is empty but input exists
                    }

                    let plannedFilterInput = searchForm.querySelector('input[name="planned_filter"]');
                    if (currentPlannedFilter) {
                        if (!plannedFilterInput) {
                            plannedFilterInput = document.createElement('input');
                            plannedFilterInput.type = 'hidden';
                            plannedFilterInput.name = 'planned_filter';
                            searchForm.appendChild(plannedFilterInput);
                        }
                        plannedFilterInput.value = currentPlannedFilter;
                    } else if (plannedFilterInput) {
                        plannedFilterInput.remove(); // Remove if currentPlannedFilter is empty but input exists
                    }

                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        sessionStorage.setItem('taskSearchSubmitted', 'true');
                        searchForm.submit();
                    }, 500);
                });
            }
        });
    </script>

</x-app-layout>
