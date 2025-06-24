<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Locatie Bewerken: ') }} {{ $location->name }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('locations.update', $location) }}" method="POST">
            @csrf
            @method('PUT')
            @include('locations._form', ['location' => $location, 'submitButtonText' => 'Wijzigingen Opslaan'])
        </form>
    </div>
</x-app-layout> 