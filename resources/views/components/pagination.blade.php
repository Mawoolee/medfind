@if ($paginator->hasPages())
    @php
        $paginator->appends(request()->query());
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, min($currentPage - 2, $lastPage - 4));
        $endPage = min($lastPage, $startPage + 4);
    @endphp
    <nav role="navigation" aria-label="Pagination Navigation" class="ml-auto mt-4 mb-6 flex w-fit justify-end overflow-hidden rounded-xl bg-white p-3 shadow-lg">
        <div class="flex flex-wrap items-center justify-end gap-1" aria-label="Pagination pages">
            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span aria-current="page" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-purple-600 bg-purple-600 px-3 text-sm font-semibold text-white">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        {{ $page }}
                    </a>
                @endif
            @endfor
        </div>
    </nav>
@endif
