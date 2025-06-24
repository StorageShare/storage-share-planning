<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nieuwe Standaardtaak') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-md sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Nieuwe Standaardtaak</h1>
                        <p class="mt-2 text-sm text-gray-600">Maak een nieuwe standaardtaak aan die kan worden gebruikt bij het aanmaken van planningen.</p>
                    </div>

                    <form method="POST" action="{{ route('default-tasks.store') }}" class="space-y-6">
                        @csrf

                        @include('default-tasks._form', [
                            'defaultTask' => new App\Models\DefaultTask(), 
                            'submitButtonText' => __('Standaardtaak Aanmaken'),
                            'locations' => $locations,
                            'selectedLocations' => old('locations', []),
                            'benodigdheden' => $benodigdheden ?? collect(),
                            'selectedBenodigdheden' => old('benodigdheden', []),
                            'availableDoorTypes' => $availableDoorTypes ?? []
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 