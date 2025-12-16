<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Nieuwe Voertuig Standaardtaak') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold">Nieuwe Voertuig Standaardtaak</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Maak een nieuwe standaardtaak aan voor voertuigen.</p>
                    </div>

                    <form method="POST" action="{{ route('default-vehicle-tasks.store') }}" class="space-y-6">
                        @csrf
                        @include('default-vehicle-tasks._form', [
                            'defaultVehicleTask' => new App\Models\DefaultVehicleTask(),
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
