@if ($paginator->hasPages())
    @php
        $paginator->appends(request()->query());
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);
    @endphp
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600">
            Showing <span class="font-medium">{{ $paginator->firstItem() ?? 0 }}</span>
            to <span class="font-medium">{{ $paginator->lastItem() ?? 0 }}</span>
            of <span class="font-medium">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex flex-wrap items-center gap-1" aria-label="Pagination pages">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 px-3 text-sm text-gray-400">
                    Previous
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    Previous
                </a>
            @endif

            @if ($startPage > 1)
                <a href="{{ $paginator->url(1) }}" class="hidden min-h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:inline-flex">1</a>
                @if ($startPage > 2)
                    <span aria-hidden="true" class="hidden min-h-10 items-center px-2 text-gray-500 sm:inline-flex">…</span>
                @endif
            @endif

            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span aria-current="page" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-purple-600 bg-purple-600 px-3 text-sm font-semibold text-white">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="hidden min-h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 sm:inline-flex">
                        {{ $page }}
                    </a>
                @endif
            @endfor

            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <span aria-hidden="true" class="hidden min-h-10 items-center px-2 text-gray-500 sm:inline-flex">…</span>
                @endif
                <a href="{{ $paginator->url($lastPage) }}" class="hidden min-h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:inline-flex">{{ $lastPage }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    Next
                </a>
            @else
                <span aria-disabled="true" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 px-3 text-sm text-gray-400">
                    Next
                </span>
            @endif
        </div>
    </nav>
@endif
