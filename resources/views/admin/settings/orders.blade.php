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
            <p class="form-hint">Used to track which platform an order came from. The note is printed in the invoice's Notes section for orders placed on that marketplace. Removing a marketplace doesn't change orders that already used it — the name and note stay on those orders.</p>
        </header>

        @if ($marketplaces->isNotEmpty())
            <div class="marketplace-grid">
                @foreach ($marketplaces as $marketplace)
                    <section class="marketplace-card">
                        <div class="marketplace-card-head">
                            <span class="marketplace-media">
                                @if ($marketplace->logo)
                                    <img src="{{ $marketplace->logoUrl() }}" alt="">
                                @else
                                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M3 16l5-5 4 4 4-4 5 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="8" cy="8.5" r="1.5" fill="currentColor"/>
                                    </svg>
                                @endif
                            </span>
                            <h3 class="marketplace-name">{{ $marketplace->name }}</h3>
                            <form method="POST" action="{{ route('admin.settings.marketplaces.destroy', $marketplace) }}" class="marketplace-remove-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="footer-text-btn">Remove</button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.marketplaces.update', $marketplace) }}" enctype="multipart/form-data" class="marketplace-card-form">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label for="marketplace_note_{{ $marketplace->id }}">Invoice note</label>
                                <textarea
                                    id="marketplace_note_{{ $marketplace->id }}"
                                    name="note"
                                    class="form-control"
                                    rows="4"
                                    maxlength="500"
                                    placeholder="Note shown on the invoice (optional)"
                                >{{ old('note', $marketplace->note) }}</textarea>
                            </div>

                            <div class="marketplace-card-footer">
                                <div class="form-group">
                                    <label for="marketplace_logo_{{ $marketplace->id }}">Logo</label>
                                    <input type="file" id="marketplace_logo_{{ $marketplace->id }}" name="logo" accept="image/*" class="form-control">
                                    <p class="form-hint">Resized to 100×100px WebP.</p>
                                </div>
                                <div class="marketplace-card-actions">
                                    @if ($marketplace->logo)
                                        <label class="form-check">
                                            <input type="checkbox" name="remove_logo" value="1">
                                            Remove logo
                                        </label>
                                    @endif
                                    <button type="submit" class="btn btn-sm btn-secondary">Save</button>
                                </div>
                            </div>
                        </form>
                    </section>
                @endforeach
            </div>
        @endif

        <section class="marketplace-card marketplace-card--new">
            <h3 class="admin-panel-title">Add a marketplace</h3>
            <form method="POST" action="{{ route('admin.settings.marketplaces.store') }}" enctype="multipart/form-data" class="marketplace-card-form">
                @csrf
                <div class="form-group">
                    <label for="marketplace_name">Name</label>
                    <input type="text" id="marketplace_name" name="name" class="form-control" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Vinted">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="marketplace_new_note">Invoice note</label>
                    <textarea id="marketplace_new_note" name="note" class="form-control" rows="4" maxlength="500" placeholder="Note shown on the invoice (optional)">{{ old('note') }}</textarea>
                    @error('note') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="marketplace-card-footer">
                    <div class="form-group">
                        <label for="marketplace_new_logo">Logo</label>
                        <input type="file" id="marketplace_new_logo" name="logo" accept="image/*" class="form-control">
                        <p class="form-hint">Resized to 100×100px WebP (optional).</p>
                        @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="marketplace-card-actions">
                        <button type="submit" class="btn btn-primary">Add marketplace</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
@endsection
