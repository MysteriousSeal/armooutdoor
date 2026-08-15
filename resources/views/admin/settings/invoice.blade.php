@extends('layouts.admin')

@section('title', 'Invoice settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Invoice</h2>
                    <p class="admin-list-lede">
                        Shown as a link at the bottom of every invoice PDF, below the legal VAT mention.
                    </p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.invoice.update') }}" class="admin-form-card admin-form-card--solo">
            @csrf
            @method('PUT')

            <h3 class="admin-panel-title">Invoice footer</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="invoice_footer_text">Footer text</label>
                    <input type="text" id="invoice_footer_text" name="invoice_footer_text" class="form-control" value="{{ old('invoice_footer_text', $setting->invoice_footer_text) }}" maxlength="255" placeholder="e.g. Retrouvez-nous en ligne">
                    @error('invoice_footer_text') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="invoice_footer_url">Footer URL</label>
                    <input type="url" id="invoice_footer_url" name="invoice_footer_url" class="form-control" value="{{ old('invoice_footer_url', $setting->invoice_footer_url) }}" maxlength="255" placeholder="https://...">
                    @error('invoice_footer_url') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="form-hint">Leave both blank to hide this line entirely.</p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
