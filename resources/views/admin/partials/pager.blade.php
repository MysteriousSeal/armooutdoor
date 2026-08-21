@if ($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-secondary" aria-disabled="true">Previous</span>
        @else
            <a class="btn btn-sm btn-secondary" href="{{ $paginator->previousPageUrl() }}">Previous</a>
        @endif

        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            @php
                // Fenêtre glissante : imprimer toutes les pages tient encore à
                // douze, plus du tout à cinquante.
                $last = $paginator->lastPage();
                $current = $paginator->currentPage();
                $window = range(max(1, $current - 2), min($last, $current + 2));
            @endphp
            <span class="admin-pagination-pages">
                @if (! in_array(1, $window, true))
                    <a class="admin-pagination-page" href="{{ $paginator->url(1) }}">1</a>
                    @if ($window[0] > 2)
                        <span class="admin-pagination-gap" aria-hidden="true">…</span>
                    @endif
                @endif

                @foreach ($window as $page)
                    @if ($page === $current)
                        <span class="admin-pagination-page is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="admin-pagination-page" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endforeach

                @if (! in_array($last, $window, true))
                    @if ($window[count($window) - 1] < $last - 1)
                        <span class="admin-pagination-gap" aria-hidden="true">…</span>
                    @endif
                    <a class="admin-pagination-page" href="{{ $paginator->url($last) }}">{{ $last }}</a>
                @endif
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
