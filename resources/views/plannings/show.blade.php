<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{-- Planning Details: {{ $planning->location->name }} op {{ $planning->planned_date->format('d-m-Y') }} --}}
                Planning Details: {{ $planning->locations->pluck('name')->join(', ') ?: 'Nog geen locatie(s)' }} op {{ $planning->planned_date->format('d-m-Y') }}
            </h2>
            <div>
                <a href="{{ route('plannings.edit', $planning) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-700 mr-2 text-sm font-medium">
                    Bewerken
                </a>
                <a href="{{ route('plannings.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 text-sm font-medium">
                    Terug naar overzicht
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-100 rounded-md shadow-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-100 rounded-md shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Geplande Datum</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->planned_date->format('l, j F Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @switch(strtolower($planning->status ?? ''))
                                        @case('open') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                        @case('completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                        @case('cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                        @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                    @endswitch
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $planning->status ?? 'Onbekend')) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Aangemaakt op</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Aangemaakt door</h3>
                            <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $planning->creator?->name ?? 'Onbekend' }}</p>
                        </div>
                    </div>
                    @if($planning->notes)
                        <div class="mt-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Notities</h3>
                            <p class="mt-1 text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $planning->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if ($planning->locations->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-center text-gray-500 dark:text-gray-400">Geen locaties toegewezen aan deze planning.</p>
                </div>
            @else
                @foreach ($planning->locations as $location)
                    <div class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Taken voor Locatie: {{ $location->name }}</h3>
                        
                        @php
                            $tasksForLocation = $planning->planningTasks->filter(function ($pt) use ($location) {
                                if ($pt->task_id && $pt->task) { // Backlog Task
                                    return $pt->task->location_id == $location->id;
                                } elseif ($pt->default_task_id && $pt->defaultTask) { // Default Task
                                    return $pt->location_id == $location->id; // Use direct location_id on PlanningTask
                                }
                                return false;
                            });
                            $totalMinutesForLocation = 0; // Initialize total minutes for this location
                        @endphp

                        @if ($tasksForLocation->isEmpty())
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <p class="text-center text-gray-500 dark:text-gray-400">Geen taken gepland voor deze locatie.</p>
                            </div>
                        @else
                            <div class="flex flex-col">
                                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Taak</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Type</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Prioriteit</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Gesch. Tijd</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Status</th>
                                                        <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Notities bij Voltooiing</th>
                                                        <th scope="col" class="relative py-3.5 px-4">
                                                            <span class="sr-only">Acties</span>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                                    @foreach ($tasksForLocation as $planningTask)
                                                        @php
                                                            $estimatedMinutes = 0;
                                                            if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                                                                $estimatedMinutes = (int)$planningTask->task->estimated_time_minutes;
                                                            } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) { 
                                                               $estimatedMinutes = (int)$planningTask->defaultTask->estimated_time_minutes;
                                                            }
                                                            $totalMinutesForLocation += $estimatedMinutes;
                                                        @endphp
                                                        <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}">
                                                            <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-normal">
                                                                <div class="font-semibold">{{ $planningTask->title }}</div>
                                                                @if($planningTask->description)
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($planningTask->description, 150) }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                                {{ $planningTask->task_id ? 'Backlog' : 'Standaard' }}
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                                @if ($planningTask->task && $planningTask->task->priority)
                                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ 
                                                                        match($planningTask->task->priority) {
                                                                            App\Enums\TaskPriority::HIGH => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                            App\Enums\TaskPriority::NORMAL => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                                            App\Enums\TaskPriority::LOW => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                                            default => 'bg-gray-100 text-gray-600'
                                                                        }
                                                                    }}">{{ $planningTask->task->priority->label() }}</span>
                                                                @elseif ($planningTask->defaultTask) {{-- Default tasks don't have priority shown here --}}
                                                                    -
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                                                {{ $estimatedMinutes > 0 ? $estimatedMinutes . ' min' : 'N/A' }}
                                                            </td>
                                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                                @if ($planningTask->completed_at)
                                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                        Voltooid op {{ $planningTask->completed_at->format('d-m-Y') }}
                                                                    </span>
                                                                @else
                                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                        Openstaand
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-normal">
                                                                {{ $planningTask->completed_notes ? Str::limit($planningTask->completed_notes, 150) : '-' }}
                                                            </td>
                                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                                @if (!$planningTask->completed_at)
                                                                    <form action="{{ route('plannings.tasks.complete', [$planning, $planningTask]) }}" method="POST" class="inline-block">
                                                                        @csrf
                                                                        {{-- Can add a small notes field here if needed for completion --}}
                                                                        {{-- <textarea name="completed_notes" placeholder="Optionele notities..."></textarea> --}}
                                                                        <button type="submit" class="px-2 py-1 text-xs text-green-600 transition-colors duration-200 rounded-md hover:bg-green-100 dark:hover:bg-gray-800 dark:text-green-400">Voltooien</button>
                                                                    </form>
                                                                @else
                                                                    <form action="{{ route('plannings.tasks.uncomplete', [$planning, $planningTask]) }}" method="POST" class="inline-block">
                                                                        @csrf
                                                                        <button type="submit" class="px-2 py-1 text-xs text-yellow-600 transition-colors duration-200 rounded-md hover:bg-yellow-100 dark:hover:bg-gray-800 dark:text-yellow-400">Heropenen</button>
                                                                    </form>
                                                                @endif
                                                                {{-- TODO: Add view/edit for completed_notes and photo upload link if needed --}}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                    <tr>
                                                        <td colspan="3" class="px-4 py-3 text-sm font-semibold text-right text-gray-700 dark:text-gray-200">Totaal geschatte tijd voor {{ $location->name }}:</td>
                                                        <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                                            @php
                                                                $hours = floor($totalMinutesForLocation / 60);
                                                                $minutes = $totalMinutesForLocation % 60;
                                                            @endphp
                                                            {{ $hours > 0 ? $hours . ' uur ' : '' }}{{ $minutes > 0 ? $minutes . ' min' : ($hours == 0 ? '0 min' : '') }}
                                                            {{ $totalMinutesForLocation == 0 && $hours == 0 ? 'N/A' : '' }}
                                                        </td>
                                                        <td colspan="3" class="px-4 py-3 text-sm"></td> {{-- Empty cells for remaining columns --}}
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

        </div>
    </div>
</x-app-layout> 