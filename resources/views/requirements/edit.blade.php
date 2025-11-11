<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
            {{ __('Requirement Bewerken') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('requirements.update', $requirement) }}" method="POST">
            @csrf
            @method('PUT')
            @include('requirements._form', [
                'requirement' => $requirement,
                'submitButtonText' => __('Requirement Bijwerken')
            ])
        </form>
    </div>
</x-app-layout>
