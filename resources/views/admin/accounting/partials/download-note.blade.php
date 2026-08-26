{{-- What the last copy taken out says about this month: never taken, taken on
     a date, or taken and since overtaken by a change. Shared by both sections;
     the decision to take one is made right under the buttons. --}}
@if ($lastDownload && $stale)
    {{-- The filed copy no longer matches the month:
         said plainly, since a journal that disagrees
         with the accounts is worse than none. --}}
    <p class="accounting-download-note is-stale">
        <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
            <path d="M12 8v5m0 3.5v.1M10.3 4.3 2.8 17.5A1.5 1.5 0 0 0 4.1 20h15.8a1.5 1.5 0 0 0 1.3-2.5L13.7 4.3a1.5 1.5 0 0 0-2.6 0z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Changed since the copy of {{ $lastDownload->created_at->format('j M Y') }} at {{ $lastDownload->created_at->format('H:i') }} — download it again</span>
    </p>
@elseif ($lastDownload)
    <p class="accounting-download-note is-filed">
        <span>
            Last downloaded {{ $lastDownload->created_at->format('j M Y') }} at {{ $lastDownload->created_at->format('H:i') }}
            @if ($lastDownload->user)
                by {{ $lastDownload->user->name }}
            @endif
        </span>
    </p>
@else
    <p class="accounting-download-note is-never">Never downloaded</p>
@endif
