<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nieuwe Standaardtaak Aanmaken') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-12">
        <form action="{{ route('default-tasks.store') }}" method="POST">
            @csrf
            @include('default-tasks._form', [
                'defaultTask' => new App\Models\DefaultTask(), 
                'submitButtonText' => __('Standaardtaak Aanmaken'),
                'locations' => $locations,
                'selectedLocations' => old('locations', [])
            ])
        </form>
    </div>
</x-app-layout> 