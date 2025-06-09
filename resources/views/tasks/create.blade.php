<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nieuwe Taak Aanmaken voor Locatie: {{ $location->name }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-12">
        <form action="{{ route('locations.tasks.store', $location) }}" method="POST" enctype="multipart/form-data">
            @csrf
            {{-- Nieuw Task model voor het formulier --}}
            @include('tasks._form', ['task' => new App\Models\Task(), 'location' => $location, 'submitButtonText' => 'Taak Aanmaken'])
        </form>
    </div>
</x-app-layout>