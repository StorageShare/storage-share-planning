<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            @if(auth()->user()->isAdmin())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="{{ route('backlog.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg block hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Openstaande taken (niet gepland)') }}</h3>
                            <p class="mt-1 text-3xl font-semibold text-gray-700 dark:text-gray-200">{{ $backlog_open_tasks }}</p>
                        </div>
                    </a>

                    <a href="{{ route('plannings.index', ['planned_date' => now()->toDateString()]) }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg block hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Planningen voor vandaag') }}</h3>
                            <p class="mt-1 text-3xl font-semibold text-gray-700 dark:text-gray-200">{{ $todays_plannings->count() }}</p>
                        </div>
                    </a>

                    <a href="{{ route('admin.tasks.review') }}" class="bg-yellow-100 dark:bg-yellow-800/50 overflow-hidden shadow-sm sm:rounded-lg block hover:bg-yellow-200 dark:hover:bg-yellow-700/60 transition duration-150 ease-in-out">
                        <div class="p-6 text-yellow-900 dark:text-yellow-100">
                            <h3 class="text-lg font-medium">{{ __('Taken ter beoordeling') }}</h3>
                            <p class="mt-1 text-3xl font-semibold">{{ $tasks_for_review_count }}</p>
                        </div>
                    </a>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Planningen voor vandaag') }}</h3>
                    @if($todays_plannings->count() > 0)
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($todays_plannings as $planning)
                                @include('plannings.partials.planning-card', ['planning' => $planning])
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-gray-500 dark:text-gray-400">Geen planningen voor vandaag.</p>
                    @endif
                </div>
            @else
                <div class="space-y-6">
                    {{-- Today's Plannings --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __("Vandaag") }}</h3>
                        @if($todays_plannings->count() > 0)
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($todays_plannings as $planning)
                                    @include('plannings.partials.planning-card', ['planning' => $planning])
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-gray-600 dark:text-gray-400">{{ __('Geen planningen voor vandaag.') }}</p>
                        @endif
                    </div>

                    {{-- Rest of the week's Plannings --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Rest van de week') }}</h3>
                         @if($plannings_rest_of_week->count() > 0)
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($plannings_rest_of_week as $planning)
                                    @include('plannings.partials.planning-card', ['planning' => $planning])
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-gray-600 dark:text-gray-400">{{ __('Geen planningen voor de rest van de week.') }}</p>
                        @endif
                    </div>

                    {{-- Next week's Plannings --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Volgende week') }}</h3>
                        @if($plannings_next_week->count() > 0)
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($plannings_next_week as $planning)
                                    @include('plannings.partials.planning-card', ['planning' => $planning])
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-gray-600 dark:text-gray-400">{{ __('Geen planningen voor volgende week.') }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
