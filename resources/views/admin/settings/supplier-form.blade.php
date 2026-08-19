@extends('layouts.admin')

@section('title', $supplier->exists ? 'Edit supplier' : 'Add supplier')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.suppliers.index') }}">Suppliers</a></p>
                    <h2 class="admin-list-title">{{ $supplier->exists ? 'Edit supplier' : 'Add supplier' }}</h2>
                    <p class="admin-list-lede">Name, website, and the usual lead time once an order is placed.</p>
                </div>
                <a href="{{ route('admin.settings.suppliers.index') }}" class="btn btn-secondary">Back to suppliers</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $supplier->exists ? route('admin.settings.suppliers.update', $supplier) : route('admin.settings.suppliers.store') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($supplier->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required maxlength="80" placeholder="e.g. Cybergun">
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" class="form-control" value="{{ old('website', $supplier->website) }}" maxlength="255" placeholder="https://…">
                @error('website') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="lead_time_days">Lead time (days)</label>
                <input type="number" id="lead_time_days" name="lead_time_days" class="form-control" value="{{ old('lead_time_days', $supplier->lead_time_days) }}" min="0" max="365" placeholder="e.g. 7">
                <p class="form-hint">How many days it typically takes this supplier to deliver once an order is placed.</p>
                @error('lead_time_days') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $supplier->exists ? 'Save changes' : 'Add supplier' }}</button>
                <a href="{{ route('admin.settings.suppliers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        @if ($supplier->exists)
            <div class="admin-form-card admin-form-card--solo" style="margin-top: 1rem">
                <h3 class="admin-panel-title">Remove</h3>
                <p class="form-hint">This only removes the supplier from this list.</p>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-modal-open="supplier-delete-modal">Remove supplier</button>
                </div>
            </div>

            <dialog id="supplier-delete-modal" class="modal" aria-labelledby="supplier-delete-title">
                <form method="POST" action="{{ route('admin.settings.suppliers.destroy', $supplier) }}">
                    @csrf
                    @method('DELETE')
                    <p class="modal-kicker">{{ $supplier->name }}</p>
                    <h3 class="modal-title" id="supplier-delete-title">Remove this supplier?</h3>
                    <p class="modal-body">This only removes the supplier from this list.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Remove supplier</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection
