@if ($paginator->hasPages())
    <nav class="flex items-center justify-between py-3">
        {{-- Results Count (optional, can be moved or styled differently if needed) --}}
        <div class="hidden sm:block">
            <p class="text-sm text-gray-700">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-medium">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-medium">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>
        </div>

        {{-- Preline Pagination Component --}}
        <div class="flex items-center gap-x-1">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <button type="button" class="min-h-[32px] min-w-[32px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1 text-xs rounded-lg text-gray-800 dark:text-gray-300 opacity-50 cursor-default" disabled>
                    <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">{!! __('pagination.previous') !!}</span>
                </button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="min-h-[32px] min-w-[32px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1 text-xs rounded-lg text-gray-800 dark:text-gray-300 hover:bg-gray-100 focus:outline-none focus:bg-gray-100">
                    <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">{!! __('pagination.previous') !!}</span>
                </a>
            @endif

            {{-- Pagination Elements --}}
            <div class="hidden md:flex items-center gap-x-1">
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="min-h-[32px] min-w-[32px] flex justify-center items-center text-gray-500 py-2 px-2.5 text-xs rounded-lg">{{ $element }}</span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <button type="button" class="min-h-[32px] min-w-[32px] flex justify-center items-center bg-blue-600 text-white py-2 px-2.5 text-xs rounded-lg focus:outline-none" aria-current="page">{{ $page }}</button>
                            @else
                                <a href="{{ $url }}" class="min-h-[32px] min-w-[32px] flex justify-center items-center text-gray-800 dark:text-gray-300 hover:bg-gray-100 py-2 px-2.5 text-xs rounded-lg focus:outline-none focus:bg-gray-100">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="min-h-[32px] min-w-[32px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1 text-xs rounded-lg text-gray-800 dark:text-gray-300 hover:bg-gray-100 focus:outline-none focus:bg-gray-100">
                    <span class="hidden sm:inline">{!! __('pagination.next') !!}</span>
                    <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <button type="button" class="min-h-[32px] min-w-[32px] py-2 px-2.5 inline-flex justify-center items-center gap-x-1 text-xs rounded-lg text-gray-800 dark:text-gray-300 opacity-50 cursor-default" disabled>
                    <span class="hidden sm:inline">{!! __('pagination.next') !!}</span>
                    <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            @endif
        </div>
    </nav>
@endif
