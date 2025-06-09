<x-app-layout>
    <x-slot name="header">
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Gebruikers</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $users->total() }} gebruikers</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Lijst van alle gebruikers in het systeem.</p>
                    </div>
                </div>

                <div class="flex flex-col mt-6">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                            <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Naam</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Email</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Rol</th>
                                            <th scope="col" class="relative py-3.5 px-4">
                                                <span class="sr-only">Acties</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                        @foreach ($users as $user)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div>
                                                        <h2 class="font-medium text-gray-800 dark:text-white ">{{ $user->name }}</h2>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-300">{{ $user->email }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                    <div class="inline px-3 py-1 text-sm font-normal rounded-full {{ match($user->role->value) {
                                                        'admin' => 'text-blue-500 bg-blue-100/60 dark:bg-gray-800',
                                                        'employee' => 'text-emerald-500 bg-emerald-100/60 dark:bg-gray-800',
                                                        'user' => 'text-gray-500 bg-gray-100/60 dark:bg-gray-800',
                                                        default => 'text-gray-500 bg-gray-100/60 dark:bg-gray-800',
                                                    } }}">
                                                        {{ $user->role->value }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('users.show', $user) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">Bekijken</a>
                                                    <a href="{{ route('users.edit', $user) }}" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Bewerken</a>
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
                        Pagina <span class="font-medium text-gray-700 dark:text-gray-100">{{ $users->currentPage() }} van {{ $users->lastPage() }}</span>
                    </div>
                    <div class="flex items-center mt-4 gap-x-4 sm:mt-0">
                        {{ $users->links('vendor.pagination.tailwind') }}
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout> 