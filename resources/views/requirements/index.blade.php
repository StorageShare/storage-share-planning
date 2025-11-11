<x-app-layout>
    <x-slot name="header">
        {{-- The old header is replaced by the new structure below --}}
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">

            {{-- Case 1: Benodigdheden list is genuinely empty, and no search is active. --}}
            @if ($requirements->isEmpty() && empty($searchTerm))
                <div class="py-6 px-4 text-center text-gray-500 dark:text-gray-400">
                    <p>Er zijn nog geen benodigdheden aangemaakt.</p>
                    <div class="mt-4">
                         <a href="{{ route('requirements.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 mx-auto text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Benodigdheid</span>
                        </a>
                    </div>
                </div>
            {{-- Case 2: Search is active OR there are requirements to show --}}
            @else
            <section class="container px-4 mx-auto">
                {{-- This part (header and search form) will always show if we are in this 'else' block --}}
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Benodigdheden</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $requirements->total() }} items</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Beheer de benodigdheden die kunnen worden toegewezen aan taken.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('requirements.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuwe Benodigdheid</span>
                        </a>
                    </div>
                </div>

                @if ($requirements->isEmpty())
                    <div class="mt-6 py-6 px-4 text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <p>Geen benodigdheden gevonden.</p>
                         <div class="mt-4">
                            <a href="{{ route('requirements.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">Terug naar overzicht</a>
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
                                                    <span>Naam</span>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <span>Beschrijving</span>
                                                </th>
                                                <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <span>Aangemaakt op</span>
                                                </th>
                                                <th scope="col" class="relative py-3.5 px-4 text-sm font-normal text-right rtl:text-right text-gray-500 dark:text-gray-400">
                                                    <span class="sr-only">Acties</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                            @foreach ($requirements as $requirement)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div>
                                                        <h2 class="font-medium text-gray-800 dark:text-white">{{ $requirement->name }}</h2>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                                    {{ Str::limit($requirement->description, 80) ?: '-' }}
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                                    {{ $requirement->created_at->format('d-m-Y H:i') }}
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('requirements.show', $requirement) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a>
                                                    <a href="{{ route('requirements.edit', $requirement) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
                                                    <form action="{{ route('requirements.destroy', $requirement) }}" method="POST" class="inline-block" onsubmit="return confirm('Weet je zeker dat je deze benodigdheid wilt verwijderen?');">
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

                    <div class="mt-6 sm:flex sm:items-center sm:justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $requirements->currentPage() }} van {{ $requirements->lastPage() }}</span>
                        </div>
                        <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                            {{ $requirements->links('vendor.pagination.tailwind') }}
                        </div>
                    </div>
                @endif {{-- Closes the @if ($requirements->isEmpty()) for table/no-results display --}}
            </section>
            @endif {{-- Closes the main @if for genuinely empty list vs. active view --}}
        </div>
    </div>
</x-app-layout>
