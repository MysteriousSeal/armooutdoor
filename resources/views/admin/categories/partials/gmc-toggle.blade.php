{{-- The same clickable pill as the product list's status column:
     check = in the Google feed, cross = kept out. Both icons are in the
     markup and the pill's own state class picks which one shows, so the
     script only has to swap classes to flip the drawing. --}}
<form method="POST" action="{{ route('admin.categories.google-feed', $category) }}" data-gmc-toggle>
    @csrf
    @method('PATCH')
    <button
        type="submit"
        class="gtin-flag gtin-flag--btn {{ $category->google_feed ? 'is-set' : 'is-missing' }}"
        title="{{ $category->google_feed ? 'In the Google feed — click to exclude' : 'Excluded from the Google feed — click to include' }}"
    >
        <svg class="gmc-icon-on" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
            <path d="m5 13 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <svg class="gmc-icon-off" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        <span class="sr-only">{{ $category->google_feed ? 'In the Google feed' : 'Excluded from the Google feed' }}</span>
    </button>
</form>
