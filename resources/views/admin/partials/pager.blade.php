@if ($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-secondary" aria-disabled="true">Previous</span>
        @else
            <a class="btn btn-sm btn-secondary" href="{{ $paginator->previousPageUrl() }}">Previous</a>
        @endif

        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <span class="admin-pagination-pages">
                @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="admin-pagination-page is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="admin-pagination-page" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            </span>
        @else
            <span class="admin-pagination-status">Page {{ $paginator->currentPage() }}</span>
        @endif

        @if ($paginator->hasMorePages())
            <a class="btn btn-sm btn-secondary" href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span class="btn btn-sm btn-secondary" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
