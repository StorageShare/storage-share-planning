<x-app-layout>
    <x-slot name="header"></x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-4 lg:px-6">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-800 dark:text-gray-100">Voertuig Bewerken</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Werk de gegevens van het voertuig bij.</p>

                @if ($errors->any())
                    <div class="mt-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
                        <div class="font-semibold">Er ging iets mis met het opslaan.</div>
                        <ul class="list-disc list-inside text-sm mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('vehicles.update', $vehicle) }}" method="POST" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')

                    @include('vehicles._form', ['types' => $types, 'vehicle' => $vehicle])

                    <div class="flex items-center justify-between">
                        <a href="{{ route('vehicles.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Terug naar overzicht</a>

                        <div class="flex items-center gap-x-3">
                            <button type="button" onclick="if (confirm('Weet je zeker dat je dit voertuig wilt verwijderen?')) document.getElementById('delete-vehicle-form').submit();" class="px-4 py-2 rounded-md border border-red-200 text-red-700 hover:bg-red-50">
                                Verwijderen
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-700 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Opslaan
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Hidden standalone delete form to avoid nested forms -->
                <form id="delete-vehicle-form" action="{{ route('vehicles.destroy', $vehicle) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
