{{-- One photo as a JPEG, converted on the way out: the shop stores WebP,
     which marketplace forms will not take. `download` names the file on the
     browser's side too, as on the Vinted page. --}}
<a
    href="{{ $href }}"
    download="{{ $filename }}"
    class="photo-download"
    title="Download {{ $filename }}, full size"
>
    <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    JPG
</a>
