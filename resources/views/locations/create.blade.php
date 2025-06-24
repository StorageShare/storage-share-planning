<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nieuwe Locatie Aanmaken') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('locations.store') }}" method="POST">
            @csrf
            @include('locations._form', ['location' => new App\Models\Location(), 'submitButtonText' => 'Locatie Aanmaken'])
        </form>
    </div>
</x-app-layout> 