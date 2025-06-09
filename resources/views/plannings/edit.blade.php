<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Planning Bewerken') }}: {{ $planning->locations->pluck('name')->join(', ') ?: 'Nog geen locatie(s) geselecteerd' }} - {{ $planning->planned_date->format('d-m-Y') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <form method="POST" action="{{ route('plannings.update', $planning) }}">
            @csrf
            @method('PUT')
            @include('plannings._form')
        </form>
    </div>
</x-app-layout> 