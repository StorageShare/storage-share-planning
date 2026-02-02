<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Externe taak</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-4 lg:px-6">
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $task->title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Locatie: {{ $task->location?->name ?? '-' }}</p>
                        </div>
                        <div class="flex items-center gap-x-3">
                            <a href="{{ route('external-backlog.edit', $task) }}" class="px-3 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                Bewerken
                            </a>
                            <span class="px-3 py-1 text-xs text-blue-600 bg-blue-100 rounded-full dark:bg-gray-700 dark:text-blue-400">{{ $task->status?->label() ?? $task->status }}</span>
                        </div>
                    </div>

                    @if (! empty($task->description))
                        <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">{{ $task->description }}</p>
                    @endif

                    <div class="mt-4 grid gap-2 text-sm text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                        <div>Deadline: {{ $task->external_deadline_at ? $task->external_deadline_at->format('d-m-Y H:i') : '-' }}</div>
                        <div>Aangemaakt: {{ $task->created_at?->format('d-m-Y H:i') }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white">Status aanpassen</h4>
                    <form method="POST" action="{{ route('external-backlog.status.update', $task) }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="py-2 px-3 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($statusOptions as $statusCase)
                                <option value="{{ $statusCase->value }}" {{ $task->status?->value === $statusCase->value ? 'selected' : '' }}>
                                    {{ $statusCase->label() }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Bijwerken</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white">Opmerkingen</h4>

                    <div class="mt-4 space-y-4">
                        @forelse ($task->comments as $comment)
                            <div class="text-sm p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $comment->user->name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at->format('d-m-Y H:i') }}</span>
                                </div>
                                <div class="text-gray-700 dark:text-gray-300">
                                    {{ $comment->comment }}
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nog geen opmerkingen.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('external-backlog.comments.store', $task) }}" class="mt-6">
                        @csrf
                        <textarea name="comment" rows="3" class="w-full py-2 px-3 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Plaats een opmerking..." required></textarea>
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Opmerking plaatsen</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
