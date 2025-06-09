<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Standaardtaak Bewerken: ') }} {{ $defaultTask->title }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-12">
        <form action="{{ route('default-tasks.update', $defaultTask) }}" method="POST">
            @csrf
            @method('PUT')
            @include('default-tasks._form', [
                'defaultTask' => $defaultTask, 
                'submitButtonText' => 'Wijzigingen Opslaan',
                'locations' => $locations,
                'selectedLocations' => old('locations', $selectedLocations)
            ])
        </form>
    </div>
</x-app-layout> 