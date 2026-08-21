@if ($paginator->hasPages())
    @php
        // Une fenêtre glissante autour de la page courante : une catégorie de
        // 600 produits afficherait sinon trente numéros à la file.
        $last = $paginator->lastPage();
        $current = $paginator->currentPage();
        $window = range(max(1, $current - 2), min($last, $current + 2));
    @endphp

    <nav class="store-pager" aria-label="{{ __('store.pagination_label') }}">
        @if ($paginator->onFirstPage())
            <span class="store-pager-arrow is-disabled" aria-hidden="true">{{ __('store.pagination_previous') }}</span>
        @else
            <a class="store-pager-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                {{ __('store.pagination_previous') }}
            </a>
        @endif

        <ol class="store-pager-pages">
            @if (! in_array(1, $window, true))
                <li><a class="store-pager-page" href="{{ $paginator->url(1) }}">1</a></li>
                @if ($window[0] > 2)
                    <li><span class="store-pager-gap" aria-hidden="true">…</span></li>
                @endif
            @endif

            @foreach ($window as $page)
                <li>
                    @if ($page === $current)
                        <span class="store-pager-page is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="store-pager-page" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                </li>
            @endforeach

            @if (! in_array($last, $window, true))
                @if ($window[count($window) - 1] < $last - 1)
                    <li><span class="store-pager-gap" aria-hidden="true">…</span></li>
                @endif
                <li><a class="store-pager-page" href="{{ $paginator->url($last) }}">{{ $last }}</a></li>
            @endif
        </ol>

        @if ($paginator->hasMorePages())
            <a class="store-pager-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next">
                {{ __('store.pagination_next') }}
            </a>
        @else
            <span class="store-pager-arrow is-disabled" aria-hidden="true">{{ __('store.pagination_next') }}</span>
        @endif

        <p class="store-pager-status">
            {{ __('store.pagination_status', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
    </nav>
@endif
