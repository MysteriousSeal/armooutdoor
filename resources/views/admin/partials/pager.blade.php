@if ($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-secondary" aria-disabled="true">Previous</span>
        @else
            <a class="btn btn-sm btn-secondary" href="{{ $paginator->previousPageUrl() }}">Previous</a>
        @endif

        <span class="admin-pagination-status">Page {{ $paginator->currentPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-sm btn-secondary" href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span class="btn btn-sm btn-secondary" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
