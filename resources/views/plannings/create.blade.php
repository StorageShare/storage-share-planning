<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nieuwe Planning Aanmaken') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8 py-12">
        <form method="POST" action="{{ route('plannings.store') }}">
            @csrf
            @include('plannings._form', ['isCreate' => true])
        </form>
        @include('plannings._quick_task_modal', ['isCreate' => true])
        @include('plannings._map_modal')
    </div>
</x-app-layout>
