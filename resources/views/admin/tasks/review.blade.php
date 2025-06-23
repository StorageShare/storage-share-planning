<x-app-layout>
    <x-slot name="header">
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <section class="container px-4 mx-auto">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Te Beoordelen Items</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-800 dark:text-blue-400">{{ $tasks_to_review->count() }} items</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Taken en end checklist items die wachten op beoordeling, gesorteerd op voltooiingsdatum.</p>
                    </div>
                </div>

                <div class="flex flex-col mt-6">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                            <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Titel</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Locatie</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Voltooid door</th>
                                            <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">Datum Voltooid</th>
                                            <th scope="col" class="relative py-3.5 px-4">
                                                <span class="sr-only">Acties</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($tasks_to_review as $review_item)
                                            <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} dark:{{ $loop->odd ? 'bg-gray-900' : 'bg-gray-800' }}
                                                {{ $review_item->status_type === 'skipped' ? 'border-l-4 border-yellow-400' : '' }}">
                                                <td class="px-4 py-4 text-sm font-medium whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <h2 class="font-medium text-gray-800 dark:text-white">{{ $review_item->title }}</h2>
                                                        @if($review_item->status_type === 'skipped')
                                                            <span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full">
                                                                Overgeslagen
                                                            </span>
                                                        @elseif($review_item->status_type === 'end_checklist')
                                                            <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 rounded-full">
                                                                End Checklist
                                                            </span>
                                                        @else
                                                            <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 rounded-full">
                                                                Review
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-300">{{ $review_item->location }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-300">{{ $review_item->completed_by ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-300">
                                                    {{ $review_item->completed_at ? \Carbon\Carbon::parse($review_item->completed_at)->format('d-m-Y H:i') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-4 text-sm whitespace-nowrap text-right">
                                                    <a href="{{ route('admin.tasks.show', ['type' => $review_item->type, 'id' => $review_item->item->id]) }}" class="px-2 py-1 text-xs text-blue-600 transition-colors duration-200 rounded-md hover:bg-blue-100 dark:hover:bg-gray-800 dark:text-blue-400">
                                                        Beoordelen
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-4 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                                                    Geen items om te beoordelen.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout> 