<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Locatie: ') }} {{ $location->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="mb-6 flex justify-between items-center">
                        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Openstaande Taken voor {{ $location->name }}</h1>
                        <div>
                            <a href="{{ route('locations.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Terug naar Locatie Overzicht
                            </a>
                        </div>
                    </div>

                    @if ($open_tasks && $open_tasks->count() > 0)
                        <div class="flex flex-col mt-6">
                            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                    <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Titel</th>
                                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Omschrijving</th>
                                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Status</th>
                                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Deadline</th>
                                                    <th scope="col" class="relative py-3.5 px-4">
                                                        <span class="sr-only">Acties</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                                @foreach ($open_tasks as $task)
                                                <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800/50' }}">
                                                    <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $task->title }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">{{ Str::limit($task->description, 50) }}</td>
                                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs rounded-full {{ $task->status === 'open' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($task->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300') }}">
                                                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">{{ $task->deadline ? $task->deadline->format('d-m-Y') : 'N.v.t.' }}</td>
                                                    <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                        {{-- <a href="{{ route('tasks.show', $task) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a> --}}
                                                        <a href="{{ route('tasks.edit', $task) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                        {{-- Placeholder for other task actions like delete if needed --}}
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if ($open_tasks->hasPages())
                            <div class="mt-6">
                                {{ $open_tasks->links() }}
                            </div>
                        @endif
                    @else
                        <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                            <p>Er zijn momenteel geen openstaande taken voor deze locatie.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 