{{-- The free-shipping strip and the welcome-code band, above the site
     header. Included at the top level of the homepage, category and product
     views, so its pushes land in the layout's head and topbar stacks. --}}
@php
    $freeShippingAmount ??= \App\Models\ShippingSetting::current()->freeShippingLabel();
    $welcomeCode = \App\Models\DiscountCode::welcomeBannerCodeFor(auth()->user());
@endphp

@if ($freeShippingAmount || $welcomeCode)
    @push('head')
        <link rel="stylesheet" href="{{ versioned_asset('css/top-strips.css') }}">
    @endpush
@endif

@if ($freeShippingAmount)
    @push('topbar')
        {{-- Only rendered when there is a real figure to name: a promise of
             free shipping with no threshold behind it is worse than silence. --}}
        <aside class="ship-strip" aria-label="{{ __('store.home_ship_banner_title', ['amount' => $freeShippingAmount]) }}">
            <p class="ship-strip-inner">
                {{-- A parcel drawn in line rather than the catalogue's
                     filled silhouette: at this size a solid shape becomes a
                     blot, and the lines of travel say "on its way" where the
                     truck only said "truck". --}}
                <svg class="ship-strip-icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
                    <path d="M1.6 9.4h3.6M0.8 12.6h3.2M2.4 15.8h2.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".65"/>
                    <path d="M8.4 8.2 14.6 5l6.2 3.2v7.6L14.6 19l-6.2-3.2V8.2Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="m8.4 8.2 6.2 3.2 6.2-3.2M14.6 11.4V19" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                <span class="ship-strip-copy">{!! __('store.home_ship_strip', ['amount' => '<b class="ship-strip-amount">'.e($freeShippingAmount).'</b>']) !!}</span>
                <span class="ship-strip-note">{{ __('store.home_ship_strip_note') }}</span>
            </p>
        </aside>
    @endpush
@endif

@if ($welcomeCode)
    @push('topbar')
        {{-- Pushed after the shipping strip, so it sits right under it. The
             amount comes from the code itself: edit the code in the back
             office and the banner follows. --}}
        <aside class="ship-strip ship-strip--welcome" aria-label="{{ __('store.home_welcome_strip', ['amount' => $welcomeCode->customerLabel()]) }}">
            <p class="ship-strip-inner">
                {{-- A price tag, drawn in the same line weight as the parcel above. --}}
                <svg class="ship-strip-icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
                    <path d="M3.5 12.6V4.5a1 1 0 0 1 1-1h8.1l8 8a1.4 1.4 0 0 1 0 2l-7.1 7.1a1.4 1.4 0 0 1-2 0l-8-8Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <circle cx="8.2" cy="8.2" r="1.6" fill="none" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <span class="ship-strip-copy">{!! __('store.home_welcome_strip', ['amount' => '<b class="ship-strip-amount">'.e($welcomeCode->customerLabel()).'</b>']) !!}</span>
                <span class="ship-strip-note">{!! __('store.home_welcome_strip_note', ['code' => '<code class="welcome-strip-code">'.e($welcomeCode->code).'</code>']) !!}</span>
            </p>
        </aside>
    @endpush
@endif
