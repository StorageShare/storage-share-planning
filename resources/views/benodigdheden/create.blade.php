<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
            {{ __('Nieuwe Benodigdheid Aanmaken') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-12">
        <form action="{{ route('benodigdheden.store') }}" method="POST">
            @csrf
            @include('benodigdheden._form', [
                'benodigdheid' => new App\Models\Benodigdheid(), 
                'submitButtonText' => __('Benodigdheid Aanmaken')
            ])
        </form>
    </div>
</x-app-layout> 