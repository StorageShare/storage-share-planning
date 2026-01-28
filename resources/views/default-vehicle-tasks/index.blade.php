<x-app-layout>
    <x-slot name="header"></x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Voertuig Standaardtaken</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $defaults->total() }} taken</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle standaardtaken voor voertuigen.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('default-vehicle-tasks.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Voertuig Standaardtaak</span>
                        </a>
                    </div>
                </div>

                @php
                    $perPage = (string) request('per_page', '15');
                @endphp
                <form action="{{ route('default-vehicle-tasks.index') }}" method="GET" class="mt-6">
                    <div class="flex items-center gap-x-3">
                        <div class="flex items-center gap-x-2">
                            <label for="default-vehicle-per-page" class="text-xs text-gray-500 dark:text-gray-300">Items per pagina</label>
                            <select id="default-vehicle-per-page" name="per_page" class="py-1.5 pl-2 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40" onchange="this.form.submit()">
                                <option value="15" {{ $perPage === '15' ? 'selected' : '' }}>15</option>
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
                            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Zoek voertuig standaardtaak..." class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">
                            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">
                        </div>
                    </div>
                </form>

                @if ($defaults->isEmpty() && !empty($search))
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen resultaten voor "{{ $search }}".</p>
                        <div class="mt-4">
                            <a href="{{ route('default-vehicle-tasks.index', array_filter(['per_page' => request('per_page')])) }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Wis zoekopdracht</a>
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
                                                <th class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('default-vehicle-tasks.index', ['search' => $search, 'sort_by' => 'title', 'sort_direction' => ($sortBy == 'title' && $sortDirection == 'asc') ? 'desc' : 'asc', 'per_page' => request('per_page')]) }}" class="flex items-center gap-x-2">
                                                        <span>Titel</span>
                                                        @if ($sortBy == 'title') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th class="px-4 py-3.5 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Actief</th>
                                                <th class="px-4 py-3.5 text-sm font-normal text-left text-gray-500 dark:text-gray-400">
                                                    <a href="{{ route('default-vehicle-tasks.index', ['search' => $search, 'sort_by' => 'created_at', 'sort_direction' => ($sortBy == 'created_at' && $sortDirection == 'desc') ? 'asc' : 'desc', 'per_page' => request('per_page')]) }}" class="flex items-center gap-x-2">
                                                        <span>Aangemaakt op</span>
                                                        @if ($sortBy == 'created_at') <x-sort-icon :direction="$sortDirection" /> @endif
                                                    </a>
                                                </th>
                                                <th class="py-3.5 px-4 text-sm font-normal text-right text-gray-500 dark:text-gray-400">Acties</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                            @forelse ($defaults as $item)
                                                <tr>
                                                    <td class="px-4 py-4 text-sm font-medium text-gray-700 whitespace-nowrap dark:text-gray-200">{{ $item->title }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                                        @if($item->active)
                                                            <span class="inline-flex items-center gap-x-1.5 py-0.5 px-2 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">Actief</span>
                                                        @else
                                                            <span class="inline-flex items-center gap-x-1.5 py-0.5 px-2 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Inactief</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">{{ $item->created_at->format('d-m-Y H:i') }}</td>
                                                    <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                        <div class="flex items-center justify-end gap-x-3">
                                                            <a href="{{ route('default-vehicle-tasks.edit', $item) }}" class="text-blue-600 hover:text-blue-800">Bewerken</a>
                                                            <form action="{{ route('default-vehicle-tasks.destroy', $item) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Er zijn nog geen voertuig standaardtaken.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @if($defaults->hasPages())
                            <div class="mt-4">{{ $defaults->links() }}</div>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
