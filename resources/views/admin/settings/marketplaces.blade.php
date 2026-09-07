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
                        What the shop says on its own pages about the places it also sells on.
                    </p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.marketplaces.update') }}" class="admin-form-card admin-form-card--solo">
            @csrf
            @method('PUT')

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
                <label class="form-check">
                    <input type="checkbox" name="naturabuy_on_home" value="1" @checked(old('naturabuy_on_home', $setting->naturabuy_on_home))>
                    <span>Show the block on the home page</span>
                </label>
                <p class="form-hint">
                    Nothing appears until the URL, the rating and the count are all filled: a block
                    that claims a standing has to carry the figures it rests on.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
