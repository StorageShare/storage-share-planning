<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        <div class="flex justify-between items-start">
            <div>
                <h4 class="text-lg text-gray-900 dark:text-gray-400 mb-2 font-bold">{{ $planning->planned_date->format('d-m-Y') }}</h4>
            </div>
            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full whitespace-nowrap
                @switch(strtolower($planning->status ?? ''))
                    @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                    @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                    @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                    @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                @endswitch
            ">
                {{ ucfirst(str_replace('_', ' ', $planning->status ?? 'Onbekend')) }}
            </span>
        </div>

        <div class="mt-4">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <strong>Locaties:</strong> <br>
                @foreach($planning->locations as $location)
                • {{ $location->name }}<br>
                @endforeach
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <strong>Uitvoerders:</strong><br>
                @foreach($planning->users as $user)
                • {{ $user->name }}<br>
                @endforeach
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <strong>Taken:</strong> {{ $planning->planningTasks->count() }}
            </p>
        </div>

        <div class="px-6 pt-4 pb-2 flex justify-end space-x-2">
            @if(Auth::user()->isAdmin())
                <a href="{{ route('plannings.show', $planning) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                    Bekijken
                </a>
                <a href="{{ route('plannings.edit', $planning) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                    Bewerken
                </a>
            @elseif($planning->users->contains(Auth::user()))
                <a href="{{ route('my-planning.planning', $planning) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 focus:outline-none focus:border-green-700 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                    Start Planning
                </a>
            @endif
        </div>
    </div>
</div>