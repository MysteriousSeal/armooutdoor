@php
    // Shared by the basket and by an order awaiting dispatch. The states are
    // the same; only the tense differs, an order having already been placed.
    $ageContext = $ageContext ?? 'cart';
    $ageRestrictedCount = count($ageRestrictedNames);
    $ageTitleKey = 'store.'.$ageContext.'_age_title';

    // The copy an order overrides; the rest reads the same either way.
    $ageMessageKey = fn (string $state): string => $ageContext === 'order'
        && in_array($state, ['none', 'pending', 'expired', 'rejected'], true)
            ? 'store.order_age_'.$state
            : 'store.cart_age_'.$state;

    // Guests have no status to show, so they get the requirement and a way to
    // deal with it. Everyone else gets where they stand and what follows.
    $state = auth()->check() ? ($identityStatus['state'] ?? 'none') : 'guest';

    $cta = match ($state) {
        'guest' => ['label' => __('store.cart_age_guest_cta'), 'url' => localized_route('login')],
        'none' => ['label' => __('store.cart_age_none_cta'), 'url' => localized_route('account.documents.index')],
        'expired', 'rejected' => ['label' => __('store.cart_age_expired_cta'), 'url' => localized_route('account.documents.index')],
        default => null,
    };

    $message = match ($state) {
        'guest' => __('store.cart_age_guest'),
        'pending' => __($ageMessageKey('pending')),
        'expired' => __($ageMessageKey('expired'), ['date' => $identityStatus['at']?->translatedFormat('d F Y')]),
        'rejected' => __($ageMessageKey('rejected')),
        'verified' => $identityStatus['until']
            ? __('store.cart_age_verified', ['date' => $identityStatus['until']->translatedFormat('d F Y')])
            : __('store.cart_age_verified_undated'),
        default => __($ageMessageKey('none')),
    };
@endphp

{{-- The cart removes a line without reloading, so the notice has to keep up:
     the two plural forms travel with it rather than being fetched back. --}}
<aside
    class="cart-age cart-age--{{ $state }}"
    aria-label="{{ trans_choice($ageTitleKey, $ageRestrictedCount) }}"
    data-cart-age
    data-title-one="{{ trans_choice($ageTitleKey, 1) }}"
    data-title-many="{{ trans_choice($ageTitleKey, 2) }}"
    data-label-one="{{ trans_choice('store.cart_age_items', 1) }}"
    data-label-many="{{ trans_choice('store.cart_age_items', 2) }}"
>
    <span class="cart-age-mark" aria-hidden="true">-18</span>

    <div class="cart-age-copy">
        <p class="cart-age-title">{{ trans_choice($ageTitleKey, $ageRestrictedCount) }}</p>
        <p class="cart-age-items-label">{{ trans_choice('store.cart_age_items', $ageRestrictedCount) }}</p>
        {{-- A list rather than a run of commas: these names carry commas of
             their own, and two of them read as four. --}}
        <ul class="cart-age-items">
            @foreach ($ageRestrictedNames as $id => $name)
                <li data-product-id="{{ $id }}">{{ $name }}</li>
            @endforeach
        </ul>
        <p class="cart-age-message">{{ $message }}</p>
    </div>

    @if ($cta)
        <a href="{{ $cta['url'] }}" class="btn btn-secondary cart-age-cta">{{ $cta['label'] }}</a>
    @endif
</aside>
