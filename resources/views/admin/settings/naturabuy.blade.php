@extends('layouts.admin')

@section('title', 'Marketplace settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Marketplaces</h2>
                    <p class="admin-list-lede">
                        What the shop says on its own pages about the marketplaces it also sells on.
                    </p>
                </div>
            </div>
        </header>

        {{-- Two marketplaces, two cards, one Save: they are saved together
             because they are one row, but they are read one at a time and a
             single card ran them into each other. --}}
        <form method="POST" action="{{ route('admin.settings.naturabuy.update') }}" class="admin-settings-stack">
            @csrf
            @method('PUT')

            <section class="admin-form-card">
                <h3 class="admin-panel-title">NaturaBuy</h3>

                <div class="form-group">
                    <label for="naturabuy_url">Seller shop URL</label>
                    <input
                        type="url"
                        id="naturabuy_url"
                        name="naturabuy_url"
                        class="form-control"
                        value="{{ old('naturabuy_url', $setting->naturabuy_url) }}"
                        placeholder="https://www.naturabuy.fr/..."
                    >
                    @error('naturabuy_url') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="form-hint">
                        The shop page, not a product listing. The home page links it in a new tab and
                        in <code>nofollow</code>, so no ranking signal goes to a marketplace the shop
                        also competes with.
                    </p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="naturabuy_rating">Seller rating</label>
                        <input
                            type="number"
                            id="naturabuy_rating"
                            name="naturabuy_rating"
                            class="form-control"
                            value="{{ old('naturabuy_rating', $setting->naturabuyRating()) }}"
                            step="0.1"
                            min="0"
                            max="5"
                            placeholder="4.9"
                        >
                        @error('naturabuy_rating') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="form-hint">Out of 5.</p>
                    </div>

                    <div class="form-group">
                        <label for="naturabuy_reviews">Number of reviews</label>
                        <input
                            type="number"
                            id="naturabuy_reviews"
                            name="naturabuy_reviews"
                            class="form-control"
                            value="{{ old('naturabuy_reviews', $setting->naturabuy_reviews) }}"
                            min="0"
                            max="999999"
                            placeholder="127"
                        >
                        @error('naturabuy_reviews') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="form-hint">The count the rating rests on.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="naturabuy_sales">Articles sold</label>
                    <input
                        type="number"
                        id="naturabuy_sales"
                        name="naturabuy_sales"
                        class="form-control"
                        value="{{ old('naturabuy_sales', $setting->naturabuy_sales) }}"
                        min="0"
                        max="9999999"
                        placeholder="1200"
                    >
                    @error('naturabuy_sales') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="form-hint">
                        Optional. The home page prints it as « Plus de X articles vendus », exactly
                        as typed: « Plus de » does the softening, and the figure stays a claim you
                        chose. Leave it empty and the line does not appear.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="naturabuy_on_home" value="1" @checked(old('naturabuy_on_home', $setting->naturabuy_on_home))>
                        <span>Show the block on the home page</span>
                    </label>
                    <p class="form-hint">
                        Nothing appears until the URL, the rating and the count are all filled: a block
                        that claims a standing has to carry the figures it rests on.
                    </p>
                </div>

            </section>

            <section class="admin-form-card">
                <h3 class="admin-panel-title">Vinted</h3>

                <div class="form-group">
                    <label for="vinted_url">Seller shop URL</label>
                    <input
                        type="url"
                        id="vinted_url"
                        name="vinted_url"
                        class="form-control"
                        value="{{ old('vinted_url', $setting->vinted_url) }}"
                        placeholder="https://www.vinted.fr/member/..."
                    >
                    @error('vinted_url') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="form-hint">
                        {{-- Say what it is for: unlike the NaturaBuy one it puts
                             nothing on the page, so an empty field looks like an
                             oversight rather than a choice. --}}
                        Nothing is shown on the site. It goes into the home page's structured data,
                        alongside the NaturaBuy shop, as another page that is plainly this same business —
                        which is what lets a search engine tie both spellings of the name to one shop.
                    </p>
                </div>

            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
