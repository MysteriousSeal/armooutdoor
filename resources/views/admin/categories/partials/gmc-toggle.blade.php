{{-- The same clickable pill as the product list's status column:
     check = in the Google feed, cross = kept out, one click flips it. --}}
<form method="POST" action="{{ route('admin.categories.google-feed', $category) }}">
    @csrf
    @method('PATCH')
    <button
        type="submit"
        class="gtin-flag gtin-flag--btn {{ $category->google_feed ? 'is-set' : 'is-missing' }}"
        title="{{ $category->google_feed ? 'In the Google feed — click to exclude' : 'Excluded from the Google feed — click to include' }}"
    >
        @if ($category->google_feed)
            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                <path d="m5 13 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="sr-only">In the Google feed</span>
        @else
            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            <span class="sr-only">Excluded from the Google feed</span>
        @endif
    </button>
</form>
