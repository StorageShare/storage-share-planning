<x-app-layout>
    <x-slot name="header">
        {{-- Header content is now managed by the new section below --}}
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-4 lg:px-6">

            {{-- Case 1: Backlog is genuinely empty, and no search/filters are active. --}}
            @if ($tasks->isEmpty() && empty($searchTerm) && empty($filters['location_id']) && empty($filters['priority']) && empty($filters['status']) && empty($filters['only_concept']))
                <div class="py-6 px-4 text-center text-gray-500 dark:text-gray-400">
                    <p>De backlog is momenteel leeg.</p>
                    <div class="mt-4">
                         <a href="{{ route('tasks.select-location') }}" class="flex items-center justify-center w-1/2 px-5 py-2 mx-auto text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Taak Toevoegen</span>
                        </a>
                    </div>
                </div>
            {{-- Case 2: Filters/search are active OR there are tasks to show (even if filters later make them empty) --}}
            @else
            <section class="container px-4 mx-auto">
                {{-- This part (header and search form) will always show if we are in this 'else' block --}}
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Taken Backlog</h2>
                            {{-- Show total only if no active search/filter is narrowing it down OR if tasks are found --}}
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $tasks->total() }} taken</span>
                        </div>
                         <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                            @if($filters['show_completed'])
                                Overzicht van alle taken, inclusief voltooide.
                            @else
                                Overzicht van alle openstaande, in uitvoering zijnde en ter beoordeling staande taken.
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('tasks.select-location') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Taak Toevoegen</span>
                        </a>
                    </div>
                </div>

                <form action="{{ route('backlog.index') }}" method="GET" id="backlogSearchForm">
                    @php
                        $perPage = (string) request('per_page', '30');
                    @endphp
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                         <div class="flex-1 md:flex md:items-center md:gap-x-4">
                             @anyrole('admin', 'facilities_coordinator')
                            <div class="inline-flex overflow-hidden bg-white border divide-x rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                                @php
                                    // Base parameters for filter links, preserving search and sort
                                    $baseParams = array_filter(request()->except('page'));
                                @endphp
                                <a href="{{ route('backlog.index', array_merge($baseParams, ['show_completed' => 'false'])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !$filters['show_completed'] ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                    Alleen Open
                                </a>
                                <a href="{{ route('backlog.index', array_merge($baseParams, ['show_completed' => 'true'])) }}"
                                class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $filters['show_completed'] ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                    Alle Taken
                                </a>
                            </div>
                            @endanyrole
                            <div class="flex items-center gap-x-2 mt-4 md:mt-0">
                                <select name="location_id" id="location_id_filter" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 md:w-auto">
                                    <option value="">Alle Locaties</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <select name="priority" id="priority_filter" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 md:w-auto">
                                    <option value="">Alle Prioriteiten</option>
                                    @foreach ($priorities as $priorityCase)
                                        <option value="{{ $priorityCase->value }}" {{ ($filters['priority'] ?? '') == $priorityCase->value ? 'selected' : '' }}>
                                            {{ $priorityCase->label() }}
                                        </option>
                                    @endforeach
                                </select>

                                <select name="status" id="status_filter" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 md:w-auto">
                                    <option value="">Alle statussen</option>
                                    @foreach (\App\Enums\TaskStatus::cases() as $statusCase)
                                        <option value="{{ $statusCase->value }}" {{ ($filters['status'] ?? '') == $statusCase->value ? 'selected' : '' }}>
                                            {{ $statusCase->label() }}
                                        </option>
                                    @endforeach
                                </select>

                                @if(!empty($filters['location_id']) || !empty($filters['priority']) || !empty($filters['status']) || !empty($filters['only_concept']))
                                    <a href="{{ route('backlog.index', array_filter(['show_completed' => $filters['show_completed'] ? 'true' : 'false', 'per_page' => request('per_page')])) }}" class="px-4 py-2 inline-flex items-center text-xs font-medium text-gray-600 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                        Wis Filters
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center mt-4 md:mt-0 gap-x-3">
                            <div class="flex items-center gap-x-2">
                                <label for="backlog-per-page" class="text-xs text-gray-500 dark:text-gray-300">Items per pagina</label>
                                <select id="backlog-per-page" name="per_page" class="py-1.5 pl-2 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40" onchange="this.form.submit()">
                                    <option value="30" {{ $perPage === '30' ? 'selected' : '' }}>30</option>
                                    <option value="50" {{ $perPage === '50' ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $perPage === '100' ? 'selected' : '' }}>100</option>
                                    <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>Alles</option>
                                </select>
                            </div>

                            <div class="relative flex items-center">
                                <span class="absolute">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </span>
                                <input type="text" name="search_term" id="backlogSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek in backlog..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">

                                {{-- Hidden inputs to carry over state --}}
                                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                                <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                                <input type="hidden" name="show_completed" value="{{ $filters['show_completed'] ? 'true' : 'false' }}">
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Now, check if tasks are empty *after* filters/search might have been applied --}}
                @if ($tasks->isEmpty())
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen taken gevonden voor de huidige selectie.</p>
                         <div class="mt-4">
                            <a href="{{ route('backlog.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis filters en zoekopdracht</a>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col mt-6">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                @php
                                                    $routeParams = array_merge(
                                                        array_filter(request()->except('page')) // Get all current query params except pagination
                                                    );
                                                @endphp
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'title', 'sort_direction' => ($sortBy == 'title' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Titel</span>
                                                        @if ($sortBy == 'title') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'location_name', 'sort_direction' => ($sortBy == 'location_name' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Locatie</span>
                                                        @if ($sortBy == 'location_name') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'priority', 'sort_direction' => ($sortBy == 'priority') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Prioriteit</span>
                                                        @if ($sortBy == 'priority') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'status', 'sort_direction' => ($sortBy == 'status' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Status</span>
                                                        @if ($sortBy == 'status') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Gepland</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'deadline', 'sort_direction' => ($sortBy == 'deadline') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Deadline</span>
                                                        @if ($sortBy == 'deadline') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('backlog.index', array_merge($routeParams, ['sort_by' => 'created_at', 'sort_direction' => ($sortBy == 'created_at') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Aangemaakt</span>
                                                        @if ($sortBy == 'created_at') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="relative py-3.5 px-4">
                                                    <span class="sr-only">Acties</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                            @foreach ($tasks as $task)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm font-medium">
                                                    <div>
                                                        <a href="{{ route('tasks.show', $task) }}" class="font-medium text-gray-800 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">{{ $task->title }}</a>
                                                        <p class="text-sm font-normal text-gray-600 dark:text-gray-400">{{ Str::limit($task->description, 60) }}</p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm">
                                                    @if($task->location)
                                                        @php
                                                            $location_name_parts = explode(',', $task->location->name, 2);
                                                        @endphp
                                                        <span class="font-medium text-black dark:text-white">{{ trim($location_name_parts[0]) }}</span>
                                                        @if(isset($location_name_parts[1]))
                                                            <br><span class="text-xs text-gray-500 dark:text-gray-400">{{ trim($location_name_parts[1]) }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">Geen locatie</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                    <a href="{{ route('tasks.edit', $task) }}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full hover:opacity-75 transition-opacity duration-200
                                                        @switch($task->priority->value)
                                                            @case(App\Enums\TaskPriority::HIGH->value) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                            @case(App\Enums\TaskPriority::NORMAL->value) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                            @case(App\Enums\TaskPriority::LOW->value) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                                        @endswitch
                                                    ">
                                                        {{ $task->priority->label() }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                     <a href="{{ route('tasks.edit', $task) }}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full hover:opacity-75 transition-opacity duration-200
                                                        @switch($task->status->value)
                                                            @case('concept') bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200 @break
                                                            @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                            @case('in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                            @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                            @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                                        @endswitch
                                                    ">
                                                        {{ $task->status->label() }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
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
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->deadline ? $task->deadline->format('d-m-Y') : '-' }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->created_at->format('d-m-Y H:i') }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
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
                                            @endforeach
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
                                {{ $tasks->links() }}
                            </div>
                        </div>
                    @endif
                @endif {{-- Closes the @if ($tasks->isEmpty()) after the form --}}
            </section>
            @endif {{-- Closes the main @if for genuinely empty backlog vs. active view --}}
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchForm = document.getElementById('backlogSearchForm');
        const searchInput = document.getElementById('backlogSearchInput');
        const locationFilter = document.getElementById('location_id_filter');
        const priorityFilter = document.getElementById('priority_filter');
        const statusFilter = document.getElementById('status_filter');

        let debounceTimer;

        function debounce(func, delay) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(func, delay);
        }

        function submitForm() {
            searchForm.submit();
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                debounce(submitForm, 500);
            });
        }

        if (locationFilter) {
            locationFilter.addEventListener('change', submitForm);
        }

        if (priorityFilter) {
            priorityFilter.addEventListener('change', submitForm);
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', submitForm);
        }
    });
</script>

</x-app-layout>
