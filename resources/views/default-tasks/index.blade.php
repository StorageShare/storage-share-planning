<x-app-layout>
    <x-slot name="header">
        {{-- The old header is replaced by the new structure below --}}
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">

            {{-- Case 1: Default tasks list is genuinely empty, and no search is active. --}}
            @if ($defaultTasks->isEmpty() && empty($searchTerm))
                <div class="py-6 px-4 text-center text-gray-500 dark:text-gray-400">
                    <p>Er zijn nog geen standaard taken aangemaakt.</p>
                    <div class="mt-4">
                         <a href="{{ route('default-tasks.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 mx-auto text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Standaardtaak</span>
                        </a>
                    </div>
                </div>
            {{-- Case 2: Search is active OR there are default tasks to show --}}
            @else
            <section class="container px-4 mx-auto">
                {{-- This part (header and search form) will always show if we are in this 'else' block --}}
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Standaard Taken</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $defaultTasks->total() }} taken</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle standaard taken.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('default-tasks.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Standaardtaak</span>
                        </a>
                    </div>
                </div>

                <form action="{{ route('default-tasks.index') }}" method="GET" id="defaultTaskSearchForm">
                    <div class="mt-6 md:flex md:items-center md:justify-between">
                        <div class="inline-flex overflow-hidden bg-white border border-gray-100 divide-x divide-gray-100 rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            {{-- No explicit filter buttons for default-tasks yet, but structure is here if needed --}}
                            {{-- Example for future: --}}
                            {{-- <a href="{{ route('default-tasks.index', array_filter(['search_term' => $searchTerm, 'sort_by' => $sortBy, 'sort_direction' => $sortDirection])) }}" ...>Bekijk alle</a> --}}
                        </div>

                        <div class="relative flex items-center w-full md:w-auto {{ $activeFilter ? 'md:justify-between' : 'md:justify-end' }}">
                            {{-- If filter controls were present, they'd go before this search div or this div would need to adjust alignment --}}
                            <div class="relative flex items-center mt-4 md:mt-0">
                                <span class="absolute">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </span>
                                <input type="text" name="search_term" id="defaultTaskSearchInput" value="{{ $searchTerm ?? '' }}" placeholder="Zoek standaardtaak..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">
    
                                {{-- Hidden inputs to preserve sort order and filter if active --}}
                                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                                <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                                @if($activeFilter)
                                    <input type="hidden" name="filter" value="{{ $activeFilter }}">
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Now, check if defaultTasks are empty *after* search might have been applied --}}
                @if ($defaultTasks->isEmpty() && !empty($searchTerm))
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen standaard taken gevonden voor "{{ $searchTerm }}".</p>
                         <div class="mt-4">
                            <a href="{{ route('default-tasks.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis zoekopdracht</a>
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
                                                    $routeParams = array_filter(['search_term' => $searchTerm, 'filter' => $activeFilter]);
                                                @endphp
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('default-tasks.index', array_merge($routeParams, ['sort_by' => 'title', 'sort_direction' => ($sortBy == 'title' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Titel</span>
                                                        @if ($sortBy == 'title') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                     <a href="{{ route('default-tasks.index', array_merge($routeParams, ['sort_by' => 'created_at', 'sort_direction' => ($sortBy == 'created_at' && $sortDirection == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center gap-x-3 focus:outline-none hover:text-gray-700">
                                                        <span>Aangemaakt op</span>
                                                        @if ($sortBy == 'created_at') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th scope="col" class="relative py-3.5 px-4 text-sm font-normal text-right rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <span class="sr-only">Acties</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                            @foreach ($defaultTasks as $defaultTask)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div>
                                                        <div class="flex items-center gap-x-2">
                                                            <h2 class="font-medium text-gray-800 dark:text-white ">{{ $defaultTask->title }}</h2>
                                                            @if($defaultTask->applies_to_all_locations)
                                                                <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-200">
                                                                    🌍 Alle locaties
                                                                </span>
                                                            @elseif($defaultTask->applies_to_lift_locations)
                                                                <span class="px-2 py-1 text-xs font-semibold text-purple-800 bg-purple-100 rounded-full dark:bg-purple-900 dark:text-purple-200">
                                                                    🛗 Alleen lift locaties
                                                                </span>
                                                            @elseif($defaultTask->applies_to_door_types)
                                                                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">
                                                                    🚪 Specifieke deur types
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <p class="text-sm font-normal text-gray-600 dark:text-gray-400">{{ Str::limit($defaultTask->description, 50) }}</p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $defaultTask->created_at->format('d-m-Y H:i') }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('default-tasks.show', $defaultTask) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a>
                                                    <a href="{{ route('default-tasks.edit', $defaultTask) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                    <form action="{{ route('default-tasks.destroy', $defaultTask) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze standaardtaak wilt verwijderen?');">
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
                            Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $defaultTasks->currentPage() }} van {{ $defaultTasks->lastPage() }}</span>
                        </div>
                        <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                            {{ $defaultTasks->appends(request()->query())->links('vendor.pagination.tailwind') }}
                        </div>
                    </div>
                @endif {{-- Closes the @if ($defaultTasks->isEmpty() && !empty($searchTerm)) for table/no-results display --}}
            </section>
            @endif {{-- Closes the main @if for genuinely empty list vs. active view --}}
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('defaultTaskSearchInput');
        const searchForm = document.getElementById('defaultTaskSearchForm');
        const searchTermFromServer = @json($searchTerm ?? '');
        let debounceTimer;

        function focusAndSetCursor(inputElement) {
            const val = inputElement.value;
            inputElement.focus();
            inputElement.value = '';
            inputElement.value = val;
        }

        if (searchInput && searchForm) {
            if (sessionStorage.getItem('defaultTaskSearchSubmitted') === 'true') {
                focusAndSetCursor(searchInput);
                sessionStorage.removeItem('defaultTaskSearchSubmitted');
            } else if (searchTermFromServer && searchTermFromServer.length > 0 && document.activeElement !== searchInput) {
                focusAndSetCursor(searchInput);
            }

            searchInput.addEventListener('input', function () {
                // Hidden inputs for sort_by, sort_direction, and filter are already in the form.
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    sessionStorage.setItem('defaultTaskSearchSubmitted', 'true'); 
                    searchForm.submit();
                }, 500); 
            });
        }
    });
</script>

</x-app-layout> 