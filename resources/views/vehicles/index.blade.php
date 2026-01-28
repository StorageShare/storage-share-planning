<x-app-layout>
    <x-slot name="header"></x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Voertuigen</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $vehicles->total() }} totaal</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Overzicht van alle voertuigen.</p>
                    </div>

                    <div class="flex items-center mt-4 gap-x-3">
                        <a href="{{ route('vehicles.create') }}" class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Nieuw Voertuig</span>
                        </a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mt-4 p-3 rounded bg-green-50 text-green-700 border border-green-200">
                        {{ session('status') }}
                    </div>
                @endif

                @php
                    $perPage = (string) request('per_page', '15');
                @endphp
                <form action="{{ route('vehicles.index') }}" method="GET" class="mt-4 flex items-center gap-x-2">
                    <label for="vehicles-per-page" class="text-xs text-gray-500 dark:text-gray-300">Items per pagina</label>
                    <select id="vehicles-per-page" name="per_page" class="py-1.5 pl-2 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40" onchange="this.form.submit()">
                        <option value="15" {{ $perPage === '15' ? 'selected' : '' }}>15</option>
                        <option value="30" {{ $perPage === '30' ? 'selected' : '' }}>30</option>
                        <option value="50" {{ $perPage === '50' ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage === '100' ? 'selected' : '' }}>100</option>
                        <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>Alles</option>
                    </select>
                </form>

                <div class="flex flex-col mt-6">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                            <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Naam</th>
                                            <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Kenteken</th>
                                            <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left text-gray-500 dark:text-gray-400">Type</th>
                                            <th scope="col" class="relative py-3.5 px-4 text-sm font-normal text-right text-gray-500 dark:text-gray-400">
                                                <span class="sr-only">Acties</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($vehicles as $vehicle)
                                            <tr>
                                                <td class="px-4 py-4 text-sm font-medium text-gray-700 whitespace-nowrap dark:text-gray-200">{{ $vehicle->name }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-300">{{ $vehicle->license_number }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-300">
                                                    @php
                                                        $type = $vehicle->type;
                                                        if ($type instanceof \App\Enums\VehicleType) {
                                                            echo e($type->label());
                                                        } elseif (is_string($type) && $type !== '') {
                                                            try {
                                                                echo e(\App\Enums\VehicleType::from($type)->label());
                                                            } catch (\ValueError $e) {
                                                                echo e('Onbekend');
                                                            }
                                                        } else {
                                                            echo e('Onbekend');
                                                        }
                                                    @endphp
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <div class="flex items-center justify-end gap-x-3">
                                                        <a href="{{ route('vehicles.edit', $vehicle) }}" class="text-blue-600 hover:text-blue-800">Bewerken</a>
                                                        <form action="{{ route('vehicles.destroy', $vehicle) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je dit voertuig wilt verwijderen?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Geen voertuigen gevonden.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if($vehicles->hasPages())
                        <div class="mt-6">
                            {{ $vehicles->links() }}
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
