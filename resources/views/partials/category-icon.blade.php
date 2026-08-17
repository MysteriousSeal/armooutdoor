@php
    $icon = $slug ?? 'default';
@endphp
<svg viewBox="0 0 48 48" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('targets')
            <circle cx="24" cy="24" r="15.5"/>
            <circle cx="24" cy="24" r="10"/>
            <circle cx="24" cy="24" r="3.2" fill="currentColor" stroke="none"/>
            <path d="M24 5.5v5M24 37.5v5M5.5 24h5M37.5 24h5"/>
            @break
        @case('range')
            <rect x="9" y="14" width="22" height="16" rx="1.5"/>
            <path d="M18 14v-3h8v3"/>
            <path d="M31 20h6.5l3 4.5V30H31"/>
            <path d="M14 22h8M14 26h5"/>
            @break
        @case('apparel')
            <path d="M16 16 24 12l8 4 5-3 4 7-6 3v15H13V24l-6-3 4-7z"/>
            <circle cx="29" cy="22" r="1.4"/>
            @break
        @case('field-gear')
            <path d="M16 18h16v18a2 2 0 0 1-2 2H18a2 2 0 0 1-2-2z"/>
            <path d="M18 18v-2.5a6 6 0 0 1 12 0V18"/>
            <path d="M16 27h16"/>
            <path d="M22 32h4"/>
            @break
        @case('everyday')
            <path d="M18 12 15 36h6L24 18l3 18h6L30 12"/>
            <path d="M18 12h12"/>
            <path d="M21 12 22.5 8h3L27 12"/>
            @break
        @case('munitions')
            <circle cx="16" cy="30" r="7"/>
            <rect x="27" y="10" width="9" height="26" rx="4.5"/>
            <path d="M29.5 16h4"/>
            @break
        @default
            <rect x="12" y="12" width="24" height="24" rx="2"/>
            <path d="M18 24h12M24 18v12"/>
    @endswitch
</svg>
