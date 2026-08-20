<nav class="account-nav" aria-label="{{ __('store.account') }}">
    <a href="{{ localized_route('account.index') }}" class="{{ request()->routeIs('account.index') ? 'is-active' : '' }}">
        {{ __('store.account') }}
    </a>
    <a href="{{ localized_route('account.profile.edit') }}" class="{{ request()->routeIs('account.profile.*') ? 'is-active' : '' }}">
        {{ __('store.account_details') }}
    </a>
    <a href="{{ localized_route('account.addresses.index') }}" class="{{ request()->routeIs('account.addresses.*') ? 'is-active' : '' }}">
        {{ __('store.account_addresses') }}
    </a>
    <a href="{{ localized_route('account.wishlist.index') }}" class="{{ request()->routeIs('account.wishlist.*') ? 'is-active' : '' }}">
        {{ __('store.account_wishlist') }}
    </a>
    <a href="{{ localized_route('orders.index') }}" class="{{ request()->routeIs('orders.*') ? 'is-active' : '' }}">
        {{ __('store.account_orders') }}
    </a>
    <a href="{{ localized_route('account.discounts.index') }}" class="{{ request()->routeIs('account.discounts.*') ? 'is-active' : '' }}">
        {{ __('store.account_discounts') }}
    </a>
    <a href="{{ localized_route('account.conversations.index') }}" class="{{ request()->routeIs('account.conversations.*') ? 'is-active' : '' }}">
        {{ __('store.account_conversations') }}
        @if ($unreadConversationCount > 0)
            <span class="account-nav-badge">{{ $unreadConversationCount }}</span>
        @endif
    </a>
</nav>
