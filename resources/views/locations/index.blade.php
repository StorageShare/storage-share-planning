<x-app-layout>
    <x-slot name="header">
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            {{-- Case 1: Locations list is genuinely empty, and no search/filters are active. --}}
            @if ($locations->isEmpty() && empty($searchTerm) && empty($activeFilter))
                <div class="py-6 px-4 text-center text-gray-500">
                    <p>Er zijn geen locaties beschikbaar (of gesynchroniseerd).</p>
                     <div class="mt-4">
                        <form action="{{ route('locations.sync') }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit" class="flex items-center justify-center w-auto px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-teal-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-teal-600 dark:hover:bg-teal-500 dark:bg-teal-600 disabled:opacity-50 disabled:pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                                    <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                                    <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.5A5.002 5.002 0 0 0 8 3M3.5 13A5.002 5.002 0 0 0 8 15c1.552 0 2.94-.707 3.857-1.818a.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.5A5.002 5.002 0 0 0 8 13"/>
                                </svg>
                                <span>Nu Synchroniseren</span>
                            </button>
                        </form>
                    </div>
                </div>
            {{-- Case 2: Filters/search are active OR there are locations to show --}}
            @else
            <section class="container px-4 mx-auto">
                {{-- This part (header and search form) will always show if we are in this 'else' block --}}
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Locaties</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $locations->total() }} locaties</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Deze locaties zijn gesynchroniseerd via de API.</p>
                    </div>
            
                    <div class="flex items-center mt-4 gap-x-3">
                         <form action="{{ route('locations.sync') }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-teal-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-teal-600 dark:hover:bg-teal-500 dark:bg-teal-600 disabled:opacity-50 disabled:pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                                    <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                                    <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.5A5.002 5.002 0 0 0 8 3M3.5 13A5.002 5.002 0 0 0 8 15c1.552 0 2.94-.707 3.857-1.818a.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.5A5.002 5.002 0 0 0 8 13"/>
                                </svg>
                                <span>Nu Synchroniseren</span>
                            </button>
                        </form>
                    </div>
                </div>
            
                <form action="{{ route('locations.index') }}" method="GET" id="locationSearchForm">
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                        <div class="inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            <a href="{{ route('locations.index', array_filter(['search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ !$activeFilter ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Bekijk alle
                            </a>
                            <a href="{{ route('locations.index', array_filter(['filter' => 'with_open_tasks', 'search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}"
                               class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:text-gray-300 {{ $activeFilter === 'with_open_tasks' ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                Alleen locaties met openstaande taken
                            </a>
                        </div>
            
                        <div class="relative flex items-center mt-4 md:mt-0">
                            <span class="absolute">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <input type="text" name="search_term" id="locationSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek locatie..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">

                            {{-- Hidden inputs to preserve sort order and filter if active --}}
                            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                            @if($activeFilter)
                                <input type="hidden" name="filter" value="{{ $activeFilter }}">
                            @endif
                        </div>
                    </div>
                </form>
                
                {{-- Now, check if locations are empty *after* filters/search might have been applied --}}
                @if ($locations->isEmpty())
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen locaties gevonden voor de huidige selectie.</p>
                        <div class="mt-4">
                            <a href="{{ route('locations.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis filters en zoekopdracht</a>
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
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('locations.index', ['sort_by' => 'name', 'sort_direction' => ($sortBy == 'name' && $sortDirection == 'asc') ? 'desc' : 'asc', 'search_term' => $searchTerm, 'filter' => $activeFilter]) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Locatie</span>
                                                        @if ($sortBy == 'name') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-center rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('locations.index', ['sort_by' => 'open_tasks_high_count', 'sort_direction' => ($sortBy == 'open_tasks_high_count') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc', 'search_term' => $searchTerm, 'filter' => $activeFilter]) }}" class="hover:text-gray-700">
                                                        Open Taken (Hoog)
                                                        @if ($sortBy == 'open_tasks_high_count') <span>{{ $sortDirection == 'asc' ? '▲' : '▼' }}</span> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-center rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('locations.index', ['sort_by' => 'open_tasks_normal_count', 'sort_direction' => ($sortBy == 'open_tasks_normal_count') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc', 'search_term' => $searchTerm, 'filter' => $activeFilter]) }}" class="hover:text-gray-700">
                                                        Open Taken (Normaal)
                                                        @if ($sortBy == 'open_tasks_normal_count') <span>{{ $sortDirection == 'asc' ? '▲' : '▼' }}</span> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-center rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('locations.index', ['sort_by' => 'open_tasks_low_count', 'sort_direction' => ($sortBy == 'open_tasks_low_count') ? (($sortDirection == 'desc') ? 'asc' : 'desc') : 'desc', 'search_term' => $searchTerm, 'filter' => $activeFilter]) }}" class="hover:text-gray-700">
                                                        Open Taken (Laag)
                                                        @if ($sortBy == 'open_tasks_low_count') <span>{{ $sortDirection == 'asc' ? '▲' : '▼' }}</span> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="relative py-3.5 px-4">
                                                    <span class="sr-only">Acties</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                            @foreach ($locations as $location)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div>
                                                        <h2 class="font-medium text-gray-800 dark:text-white ">{{ $location->name }}</h2>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                                    <div class="inline px-3 py-1 text-sm font-normal rounded-full {{ $location->open_tasks_high_count > 0 ? 'text-red-500 bg-red-100/60 dark:bg-gray-800' : 'text-gray-500 bg-gray-100/60 dark:bg-gray-800' }}">
                                                        {{ $location->open_tasks_high_count }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                                    <div class="inline px-3 py-1 text-sm font-normal rounded-full {{ $location->open_tasks_normal_count > 0 ? 'text-yellow-500 bg-yellow-100/60 dark:bg-gray-800' : 'text-gray-500 bg-gray-100/60 dark:bg-gray-800' }}">
                                                        {{ $location->open_tasks_normal_count }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                                    <div class="inline px-3 py-1 text-sm font-normal rounded-full {{ $location->open_tasks_low_count > 0 ? 'text-green-500 bg-green-100/60 dark:bg-gray-800' : 'text-gray-500 bg-gray-100/60 dark:bg-gray-800' }}">
                                                        {{ $location->open_tasks_low_count }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('locations.tasks.index', $location) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">
                                                        Bekijken
                                                    </a>
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
                            Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $locations->currentPage() }} van {{ $locations->lastPage() }}</span>
                        </div>
                        <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                            {{ $locations->appends(request()->query())->links('vendor.pagination.tailwind') }}
                        </div>
                    </div>
                @endif {{-- Closes the @if ($locations->isEmpty()) after the form --}}
            </section>
            @endif {{-- Closes the main @if for genuinely empty list vs. active view --}}
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('locationSearchInput');
        const searchForm = document.getElementById('locationSearchForm');
        const searchTermFromServer = @json($searchTerm ?? '');
        let debounceTimer;

        // Function to set focus and cursor at the end of the input
        function focusAndSetCursor(inputElement) {
            const val = inputElement.value;
            inputElement.focus();
            inputElement.value = '';
            inputElement.value = val;
        }

        if (searchInput && searchForm) {
            if (sessionStorage.getItem('locationSearchSubmitted') === 'true') {
                focusAndSetCursor(searchInput);
                sessionStorage.removeItem('locationSearchSubmitted');
            } else if (searchTermFromServer && searchTermFromServer.length > 0 && document.activeElement !== searchInput) {
                focusAndSetCursor(searchInput);
            }

            searchInput.addEventListener('input', function () {
                // Hidden inputs for sort_by, sort_direction, and filter are already in the form.
                // The JS doesn't need to add them again upon search input.
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    sessionStorage.setItem('locationSearchSubmitted', 'true'); 
                    searchForm.submit();
                }, 500); 
            });
        }
    });
</script>

</x-app-layout> 