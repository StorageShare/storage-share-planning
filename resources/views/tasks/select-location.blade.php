<!--
Dit is een nieuwe Blade view voor het selecteren van een locatie.
De daadwerkelijke content wordt door de volgende tool call geplaatst.
-->
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Selecteer een locatie om een nieuwe taak aan te maken') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-6">{{ __('Kies een Locatie') }}</h1>

            {{-- Search Form for Select Location Page --}}
            <div class="mb-4">
                <form action="{{ route('tasks.select-location') }}" method="GET" class="flex items-center space-x-2" id="selectLocationSearchForm">
                    <input type="text" name="search_term" id="selectLocationSearchInput" value="{!! htmlspecialchars($searchTerm ?? '') !!}" placeholder="Zoek locatie..." class="block w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                    <x-primary-button type="submit">
                        {{ __('Zoeken') }}
                    </x-primary-button>
                </form>
            </div>

            @if ($locations->isEmpty() && !empty($searchTerm))
                <p class="text-gray-600 dark:text-gray-400">
                    {{ __('Geen locaties gevonden voor de zoekterm ":searchTerm".', ['searchTerm' => htmlspecialchars($searchTerm)]) }}
                </p>
            @elseif ($locations->isEmpty())
                <p class="text-gray-600 dark:text-gray-400">
                    {{ __('Er zijn nog geen locaties aangemaakt.') }}
                </p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($locations as $location)
                        <a href="{{ route('locations.tasks.create', $location) }}"
                           class="group flex flex-col justify-between p-6 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-300 ease-in-out dark:bg-gray-800 dark:border-gray-700">
                            <div>
                                <h5 class="mb-2 text-xl font-semibold tracking-tight text-gray-800 group-hover:text-blue-600 dark:text-gray-200 dark:group-hover:text-blue-400">{{ $location->name }}</h5>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    @php $taskCount = $location->tasks_count ?? $location->tasks()->count(); @endphp
                                    {{ $taskCount }} {{ ($taskCount == 1) ? __('openstaande taak') : __('openstaande taken') }}
                                </p>
                            </div>
                            <span class="mt-4 inline-flex items-center justify-center gap-x-2 py-2 px-3 text-xs font-medium rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm group-hover:bg-blue-50 group-hover:text-blue-600 transition-all dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:group-hover:bg-blue-500/20 dark:group-hover:text-blue-300">
                                {{ __('Selecteer en ga verder') }}
                                <svg class="w-3 h-3 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                                </svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-8">
                <a href="{{ url()->previous(route('dashboard')) }}" class="py-2.5 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                    </svg>
                    {{ __('Terug') }}
                </a>
            </div>
        </div>
    </div>

<script>
    // Live search for select-location page
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('selectLocationSearchInput');
        const searchForm = document.getElementById('selectLocationSearchForm');
        // Use htmlspecialchars for searchTermFromServer as it's directly from URL/user input for JS comparison
        const searchTermFromServer = {!! json_encode($searchTerm ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
        let debounceTimer;

        if (searchInput && searchForm) {
            if (sessionStorage.getItem('selectLocationSearchSubmitted') === 'true') {
                searchInput.focus();
                const val = searchInput.value; 
                searchInput.value = ''; 
                searchInput.value = val; 
                sessionStorage.removeItem('selectLocationSearchSubmitted');
            } else if (searchTermFromServer && searchTermFromServer.length > 0) {
                searchInput.focus();
                const val = searchInput.value; 
                searchInput.value = ''; 
                searchInput.value = val; 
            }

            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    sessionStorage.setItem('selectLocationSearchSubmitted', 'true');
                    searchForm.submit();
                }, 500);
            });
        } 
    });
</script>

</x-app-layout> 