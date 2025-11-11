<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Taak Bewerken: {{ $task->title }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('tasks.update', $task) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('tasks._form', [
                'task' => $task,
                'location' => $task->location,
                'submitButtonText' => 'Wijzigingen Opslaan',
                'requirements' => $benodigdheden ?? collect(),
                'selectedRequirements' => old('requirements', $selectedRequirements ?? [])
            ])
        </form>
    </div>
</x-app-layout>
