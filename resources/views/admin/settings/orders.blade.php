@extends('layouts.admin')

@section('title', 'Orders settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Orders</h2>
                    <p class="admin-list-lede">Marketplaces you also sell products on.</p>
                </div>
            </div>
        </header>

        <div class="admin-form-card admin-form-card--solo">
            <h3 class="admin-panel-title">Marketplaces</h3>
            <p class="form-hint">Used to track which platform an order came from. Removing one here doesn't change orders that already used it.</p>

            @if ($marketplaces->isNotEmpty())
                <ul class="admin-check-list admin-check-list--rows">
                    @foreach ($marketplaces as $marketplace)
                        <li class="admin-check-list-row">
                            <span>{{ $marketplace->name }}</span>
                            <form method="POST" action="{{ route('admin.settings.marketplaces.destroy', $marketplace) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="footer-text-btn">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.settings.marketplaces.store') }}" class="form-row form-row--inline">
                @csrf
                <div class="form-group">
                    <label for="marketplace_name" class="sr-only">Marketplace name</label>
                    <input type="text" id="marketplace_name" name="name" class="form-control" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Vinted">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-secondary">Add marketplace</button>
            </form>
        </div>
    </div>
@endsection
