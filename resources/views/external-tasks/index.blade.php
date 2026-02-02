<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Externe taken backlog</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Externe taken</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $tasks->total() }} taken</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Taken uit externe bronnen met eigen status en opmerkingen.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3 sm:mt-0">
                        <a href="{{ route('external-backlog.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm text-white transition-colors duration-200 bg-blue-600 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe externe taak</span>
                        </a>
                    </div>
                </div>

                <form action="{{ route('external-backlog.index') }}" method="GET" class="mt-6">
                    @php
                        $perPage = (string) request('per_page', '30');
                    @endphp
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex flex-1 items-center gap-3">
                            <select name="status" class="py-2 px-3 block w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 md:w-auto">
                                <option value="">Alle statussen</option>
                                @foreach ($statusOptions as $statusCase)
                                    <option value="{{ $statusCase->value }}" {{ $statusFilter === $statusCase->value ? 'selected' : '' }}>
                                        {{ $statusCase->label() }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </span>
                                <input type="text" name="search_term" value="{{ $searchTerm ?? '' }}" placeholder="Zoek in externe taken..." class="block w-full py-2 pl-10 pr-3 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700">
                            </div>
                        </div>

                        <div class="flex items-center gap-x-2">
                            <label for="external-backlog-per-page" class="text-xs text-gray-500 dark:text-gray-400">Items per pagina</label>
                            <select id="external-backlog-per-page" name="per_page" class="py-1.5 pl-2 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700" onchange="this.form.submit()">
                                <option value="30" {{ $perPage === '30' ? 'selected' : '' }}>30</option>
                                <option value="50" {{ $perPage === '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage === '100' ? 'selected' : '' }}>100</option>
                                <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>Alles</option>
                            </select>

                            <button type="submit" class="px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Filter</button>
                        </div>
                    </div>
                </form>

                @if ($tasks->isEmpty())
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen externe taken gevonden.</p>
                    </div>
                @else
                    <div class="flex flex-col mt-6">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Titel</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Locatie</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Status</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Deadline</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Aangemaakt</th>
                                                <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Acties</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                            @foreach ($tasks as $task)
                                                <tr>
                                                    <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $task->title }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $task->location?->name ?? '-' }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $task->status?->label() ?? $task->status }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $task->external_deadline_at ? $task->external_deadline_at->format('d-m-Y H:i') : '-' }}
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $task->created_at?->format('d-m-Y H:i') }}
                                                    </td>
                                                    <td class="px-4 py-4 text-sm flex items-center gap-x-3">
                                                        <a href="{{ route('external-backlog.show', $task) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Bekijk</a>
                                                        <a href="{{ route('external-backlog.edit', $task) }}" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                            </svg>
                                                        </a>
                                                        <form action="{{ route('external-backlog.destroy', $task) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je deze externe taak wilt verwijderen?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                                </svg>
                                                            </button>
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

                    <div class="mt-6">
                        {{ $tasks->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
