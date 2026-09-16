@extends('layouts.admin')

@section('title', 'Ammunitions')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.index') }}">FFTIR</a></p>
                    <h2 class="fftir-title">Ammunitions</h2>
                    <p class="fftir-lede">Stock on hand for every caliber and denomination, and what each round costs.</p>
                </div>
                <div class="fftir-actions">
                    <a href="{{ route('admin.fftir.index') }}" class="btn btn-secondary">Back to FFTIR</a>
                    <a href="{{ route('admin.fftir.ammunitions.create') }}" class="btn btn-primary">Add ammunition</a>
                </div>
            </div>
        </header>

        @if ($ammunitions->isEmpty())
            <div class="fftir-empty">
                <p>No ammunitions logged yet.</p>
                <a href="{{ route('admin.fftir.ammunitions.create') }}" class="btn btn-primary">Add ammunition</a>
            </div>
        @else
            <div class="fftir-table-wrap">
                <table class="fftir-table">
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th>Caliber</th>
                            <th>Denomination</th>
                            <th>Stock</th>
                            <th>Price/round</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ammunitions as $ammunition)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.fftir.ammunitions.edit', $ammunition) }}" class="fftir-strong">
                                        {{ $ammunition->brand }}
                                    </a>
                                </td>
                                <td class="fftir-num">{{ $ammunition->caliber->value }}</td>
                                <td>{{ $ammunition->denomination }}</td>
                                <td class="fftir-num">{{ number_format($ammunition->quantity) }}</td>
                                @php($averagePrice = $ammunition->averageUnitPriceCents())
                                <td class="fftir-num">{{ $averagePrice !== null ? format_euros($averagePrice) : 'N/A' }}</td>
                                <td>
                                    <div class="fftir-row-actions">
                                        <button type="button" class="btn btn-sm btn-secondary" data-modal-open="stock-modal-{{ $ammunition->id }}">Add stock</button>
                                        <a href="{{ route('admin.fftir.ammunitions.edit', $ammunition) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($ammunitions as $ammunition)
                @php($stockBag = 'stock'.$ammunition->id)
                @php($stockErrors = $errors->getBag($stockBag))
                <dialog
                    id="stock-modal-{{ $ammunition->id }}"
                    class="modal fftir-modal"
                    aria-labelledby="stock-modal-title-{{ $ammunition->id }}"
                    @if ($stockErrors->any()) data-autoopen @endif
                >
                    <form method="POST" action="{{ route('admin.fftir.ammunitions.stock.store', $ammunition) }}">
                        @csrf
                        <p class="fftir-modal-kicker">{{ $ammunition->brand }} {{ $ammunition->denomination }}</p>
                        <h3 class="fftir-modal-title" id="stock-modal-title-{{ $ammunition->id }}">Add stock</h3>
                        <p class="fftir-modal-hint">Currently {{ number_format($ammunition->quantity) }} in stock.</p>

                        <div class="form-group">
                            <label for="delta-{{ $ammunition->id }}">Quantity change</label>
                            <input
                                type="number"
                                id="delta-{{ $ammunition->id }}"
                                name="delta"
                                class="form-control"
                                value="{{ $stockErrors->any() ? old('delta') : '' }}"
                                required
                                placeholder="e.g. 50 or -20"
                            >
                            <p class="form-hint">A positive number adds stock, a negative one removes it.</p>
                            @error('delta', $stockBag) <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="total_price-{{ $ammunition->id }}">Total price paid €</label>
                            <input
                                type="number"
                                id="total_price-{{ $ammunition->id }}"
                                name="total_price"
                                class="form-control"
                                value="{{ $stockErrors->any() ? old('total_price') : '' }}"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            >
                            <p class="form-hint">Only counted when adding stock. Price per round is the weighted average of every priced addition.</p>
                            @error('total_price', $stockBag) <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="note-{{ $ammunition->id }}">Note</label>
                            <input
                                type="text"
                                id="note-{{ $ammunition->id }}"
                                name="note"
                                class="form-control"
                                value="{{ $stockErrors->any() ? old('note') : '' }}"
                                maxlength="255"
                                placeholder="e.g. Bought at the range shop"
                            >
                        </div>

                        <div class="fftir-modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Record stock change</button>
                        </div>
                    </form>
                </dialog>
            @endforeach
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
