<x-app-layout>
    <x-slot name="header">
        {{-- Header content is now managed by the new section below --}}
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">

            {{-- Case 1: Backlog is genuinely empty, and no search/filters are active. --}}
            @if ($tasks->isEmpty() && empty($searchTerm) && empty($filters['location_id']) && empty($filters['priority']))
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
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle openstaande en in uitvoering zijnde taken.</p>
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
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                        <div class="flex flex-col gap-4 md:flex-row md:items-end md:gap-2">
                            <div>
                                <label for="location_id_filter" class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Locatie</label>
                                <select name="location_id" id="location_id_filter" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 md:w-auto">
                                    <option value="">Alle Locaties</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="priority_filter" class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Prioriteit</label>
                                <select name="priority" id="priority_filter" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 md:w-auto">
                                    <option value="">Alle Prioriteiten</option>
                                    @foreach ($priorities as $priorityCase)
                                        <option value="{{ $priorityCase->value }}" {{ ($filters['priority'] ?? '') == $priorityCase->value ? 'selected' : '' }}>
                                            {{ $priorityCase->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!empty($filters['location_id']) || !empty($filters['priority']) || !empty($searchTerm))
                                <div class="mt-2 md:mt-0 md:self-end">
                                    <a href="{{ route('backlog.index') }}" class="px-4 py-2 inline-flex items-center text-xs font-medium text-gray-600 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                        Wis Filters
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="relative flex items-center mt-4 md:mt-0">
                            <span class="absolute">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <input type="text" name="search_term" id="backlogSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek in backlog..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">

                            @if(request()->has('sort_by'))
                                <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                            @endif
                            @if(request()->has('sort_direction'))
                                <input type="hidden" name="sort_direction" value="{{ request('sort_direction') }}">
                            @endif
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
                                                    $routeParams = array_merge($filters, ['search_term' => $searchTerm]);
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
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div>
                                                        <a href="{{ route('tasks.show', $task) }}" class="font-medium text-gray-800 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">{{ $task->title }}</a>
                                                        <p class="text-sm font-normal text-gray-600 dark:text-gray-400">{{ Str::limit($task->description, 40) }}</p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                                    @php
                                                        $location_name_parts = explode(',', $task->location->name, 2);
                                                    @endphp
                                                    <span>{{ trim($location_name_parts[0]) }}</span>
                                                    @if(isset($location_name_parts[1]))
                                                        <br><span class="text-xs text-gray-500 dark:text-gray-400">{{ trim($location_name_parts[1]) }}</span>
                                                    @endif
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
                                                        @switch($task->status)
                                                            @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                                            @case('in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                            @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                                        @endswitch
                                                    ">
                                                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                    @if ($task->planningTasks->isNotEmpty())
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Ja</span>
                                                    @else
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Nee</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->deadline ? $task->deadline->format('d-m-Y') : '-' }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $task->created_at->format('d-m-Y H:i') }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('tasks.edit', $task) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                    <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-2 py-1 text-xs text-red-600 transition-colors duration-200 rounded-md hover:bg-red-100 dark:hover:bg-gray-800 dark:text-red-400">Verwijderen</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 sm:flex sm:items-center sm:justify-between ">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $tasks->currentPage() }} van {{ $tasks->lastPage() }}</span>
                        </div>
                        <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                            {{ $tasks->links('vendor.pagination.tailwind') }}
                        </div>
                    </div>
                @endif {{-- Closes the @if ($tasks->isEmpty()) after the form --}}
            </section>
            @endif {{-- Closes the main @if for genuinely empty backlog vs. active view --}}
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('backlogSearchInput');
        const searchForm = document.getElementById('backlogSearchForm');
        const locationFilter = document.getElementById('location_id_filter');
        const priorityFilter = document.getElementById('priority_filter');
        const searchTermFromServer = @json($searchTerm ?? '');
        
        // Get initial sort state from PHP, which includes the default sort
        const currentSortBy = @json($sortBy ?? 'deadline'); 
        const currentSortDirection = @json($sortDirection ?? 'asc');
        
        let debounceTimer;

        function prepareAndSubmitForm() {
            // Ensure hidden fields for sort order reflect the current state (either default or user-selected via URL)
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
            
            // The select dropdowns (location_id, priority) and search_term input are already part of the form.
            searchForm.submit();
        }

        if (searchInput) {
            // Logic to focus search input if it had a value or was submitted
            if (sessionStorage.getItem('backlogSearchSubmitted') === 'true') {
                searchInput.focus();
                const val = searchInput.value; 
                searchInput.value = ''; 
                searchInput.value = val; 
                sessionStorage.removeItem('backlogSearchSubmitted');
            } else if (searchTermFromServer && searchTermFromServer.length > 0 && document.activeElement !== searchInput) {
                searchInput.focus();
                const val = searchInput.value; 
                searchInput.value = ''; 
                searchInput.value = val; 
            }

            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    sessionStorage.setItem('backlogSearchSubmitted', 'true');
                    prepareAndSubmitForm();
                }, 500);
            });
        }

        if (locationFilter) {
            locationFilter.addEventListener('change', prepareAndSubmitForm);
        }

        if (priorityFilter) {
            priorityFilter.addEventListener('change', prepareAndSubmitForm);
        }
    });
</script>

</x-app-layout> 