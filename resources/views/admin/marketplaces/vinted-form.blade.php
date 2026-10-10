@extends('layouts.admin')

@section('title', $item->exists ? 'Edit Vinted item' : 'Add Vinted item')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.marketplaces.vinted') }}">Vinted</a></p>
                    <h2 class="admin-list-title">{{ $item->exists ? 'Edit Vinted item' : 'Add Vinted item' }}</h2>
                    <p class="admin-list-lede">Sold on Vinted only. It never appears on the shop.</p>
                </div>
                <a href="{{ route('admin.marketplaces.vinted') }}" class="btn btn-secondary">Back to Vinted</a>
            </div>
        </header>

        {{-- An item already there reads on two columns: what it is on the
             left, what was bought and what is left on the right. A new one
             has only its form. --}}
        @if ($item->exists)
            <div class="vinted-item-grid">
            <div class="vinted-item-col">
        @endif

        <form
            method="POST"
            action="{{ $item->exists ? route('admin.marketplaces.vinted.items.update', $item) : route('admin.marketplaces.vinted.items.store') }}"
            enctype="multipart/form-data"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($item->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $item->title) }}" required maxlength="255">
                @error('title') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="image">Main image</label>
                @if ($item->imageUrl())
                    <a href="{{ $item->imageUrl() }}" target="_blank" rel="noopener" class="vinted-item-image">
                        <img src="{{ $item->thumbnailUrl() }}" alt="" width="88" height="88" class="admin-product-thumb">
                    </a>
                @endif
                <input type="file" id="image" name="image" accept="image/*" class="form-control" @required(! $item->imageUrl())>
                @if ($item->imageUrl())
                    <p class="form-hint">Choose a file only to replace the current image.</p>
                @endif
                @error('image') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @unless ($item->exists)
                <div class="form-group">
                    <label for="purchase_total">Purchase price (€, whole lot)</label>
                    <input type="number" id="purchase_total" name="purchase_total" class="form-control" value="{{ old('purchase_total') }}" required min="0" max="500000" step="0.01" inputmode="decimal">
                    <p class="form-hint">What you paid for the whole quantity. The cost per piece is worked out from it.</p>
                    @error('purchase_total') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity available</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" value="{{ old('quantity', 1) }}" required min="1" max="100000" step="1">
                    @error('quantity') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endunless

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $item->exists ? 'Save changes' : 'Add item' }}</button>
                <a href="{{ route('admin.marketplaces.vinted') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        @if ($item->exists)
            @php($firstLotId = $item->lots->first()?->id)

            {{-- A sale made on Vinted is reported here, one piece at a
                 time, with the price it went for. --}}
            <div class="admin-form-card admin-form-card--solo vinted-item-card">
                <h3 class="admin-panel-title">Sales</h3>
                <p class="vinted-item-summary">
                    @if ($item->soldCount() === 0)
                        Nothing sold yet.
                    @else
                        <strong>{{ number_format($item->soldCount()) }}</strong> sold
                        for <strong>{{ format_euros($item->soldTotalCents()) }}</strong>:
                        <strong @class(['vinted-item-loss' => $item->profitCents() < 0])>{{ format_euros($item->profitCents()) }}</strong> profit
                        at {{ format_euros($item->unitCostCents()) }} per piece.
                    @endif
                </p>

                @if ($item->sales->isNotEmpty())
                    <div class="admin-table-wrap">
                        <table class="admin-table vinted-item-table">
                            <thead>
                                <tr>
                                    <th>Sold</th>
                                    <th class="admin-table-num">Sold price</th>
                                    <th class="admin-table-num" title="Sold price less the average cost per piece">Margin</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item->sales as $sale)
                                    @php($margin = $item->marginCents($sale))
                                    <tr>
                                        <td class="vinted-item-date">{{ $sale->created_at->locale('en')->isoFormat('D MMM YYYY') }}</td>
                                        @if ($margin === null)
                                            {{-- Reported before prices were asked for. --}}
                                            <td class="admin-table-num"><span class="nb-none" title="No price was recorded for this sale">—</span></td>
                                            <td class="admin-table-num"><span class="nb-none">—</span></td>
                                        @else
                                            <td class="admin-table-num">{{ format_euros($sale->price_cents) }}</td>
                                            <td class="admin-table-num{{ $margin < 0 ? ' vinted-item-loss' : '' }}">{{ format_euros($margin) }}</td>
                                        @endif
                                        <td>
                                            <div class="admin-table-actions admin-table-actions--slim">
                                                <button type="button" class="btn btn-sm btn-secondary" data-modal-open="vinted-sale-delete-modal-{{ $sale->id }}">Remove</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Nothing left, nothing to sell: the button goes with the stock. --}}
                @unless ($item->isSoldOut())
                    <div class="vinted-item-action">
                        <button type="button" class="btn btn-primary" data-modal-open="vinted-item-sold-modal">Sold</button>
                        <span class="form-hint">Takes one piece off the stock and asks what it sold for.</span>
                    </div>
                @endunless
            </div>

            <div class="admin-form-card admin-form-card--solo vinted-item-card">
                <h3 class="admin-panel-title">Remove</h3>
                <p class="form-hint">Deletes the item and its image from this list. This cannot be undone.</p>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-modal-open="vinted-item-delete-modal">Remove item</button>
                </div>
            </div>
            </div>

            <div class="vinted-item-col">
            {{-- Figures, not fields: what was paid and how many there are
                 change through a lot or a sale, never by retyping. --}}
            <div class="admin-form-card admin-form-card--solo vinted-item-card">
                <h3 class="admin-panel-title">Stock</h3>
                <p class="vinted-item-summary">
                    <strong>{{ number_format($item->quantity) }}</strong> available of
                    <strong>{{ number_format($item->lot_quantity) }}</strong> bought,
                    for <strong>{{ format_euros($item->purchase_total_cents) }}</strong> in all:
                    <strong>{{ format_euros($item->unitCostCents()) }}</strong> per piece on average.
                </p>

                <div class="admin-table-wrap">
                    <table class="admin-table vinted-item-table">
                        <thead>
                            <tr>
                                <th>Bought</th>
                                <th class="admin-table-num">Quantity</th>
                                <th class="admin-table-num">Purchase price</th>
                                <th class="admin-table-num">Per piece</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item->lots as $lot)
                                <tr>
                                    <td class="vinted-item-date">{{ $lot->created_at->locale('en')->isoFormat('D MMM YYYY') }}</td>
                                    <td class="admin-table-num">{{ number_format($lot->quantity) }}</td>
                                    <td class="admin-table-num">{{ format_euros($lot->purchase_total_cents) }}</td>
                                    <td class="admin-table-num">{{ format_euros($lot->unitCostCents()) }}</td>
                                    <td>
                                        {{-- The first lot is the item itself: it goes only with it. --}}
                                        @if ($lot->id !== $firstLotId)
                                            <div class="admin-table-actions admin-table-actions--slim">
                                                <button type="button" class="btn btn-sm btn-secondary" data-modal-open="vinted-lot-delete-modal-{{ $lot->id }}">Remove</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('admin.marketplaces.vinted.items.lots.store', $item) }}"
                class="admin-form-card admin-form-card--solo vinted-item-card"
            >
                @csrf
                <h3 class="admin-panel-title">Add stock</h3>
                <p class="form-hint">More of the same article bought: the pieces and their price are added to the figures above.</p>

                <div class="form-group">
                    <label for="lot_quantity">Quantity to add</label>
                    <input type="number" id="lot_quantity" name="quantity" class="form-control" value="{{ old('quantity') }}" required min="1" max="100000" step="1">
                    @error('quantity') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="lot_purchase_total">Purchase price (€, whole lot)</label>
                    <input type="number" id="lot_purchase_total" name="purchase_total" class="form-control" value="{{ old('purchase_total') }}" required min="0" max="500000" step="0.01" inputmode="decimal">
                    <p class="form-hint">What you paid for this added quantity, not for one piece.</p>
                    @error('purchase_total') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add to stock</button>
                </div>
            </form>
            </div>
            </div>

            @foreach ($item->lots as $lot)
                @continue($lot->id === $firstLotId)
                <dialog id="vinted-lot-delete-modal-{{ $lot->id }}" class="modal" aria-labelledby="vinted-lot-delete-title-{{ $lot->id }}">
                    <form method="POST" action="{{ route('admin.marketplaces.vinted.items.lots.destroy', [$item, $lot]) }}">
                        @csrf
                        @method('DELETE')
                        <p class="modal-kicker">{{ $item->title }}</p>
                        <h3 class="modal-title" id="vinted-lot-delete-title-{{ $lot->id }}">Remove this lot?</h3>
                        <p class="modal-body">
                            {{ number_format($lot->quantity) }} {{ $lot->quantity === 1 ? 'piece' : 'pieces' }} and
                            {{ format_euros($lot->purchase_total_cents) }} are taken back off the item.
                        </p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Remove lot</button>
                        </div>
                    </form>
                </dialog>
            @endforeach

            @unless ($item->isSoldOut())
                <dialog id="vinted-item-sold-modal" class="modal" aria-labelledby="vinted-item-sold-title">
                    <form method="POST" action="{{ route('admin.marketplaces.vinted.items.sold', $item) }}">
                        @csrf
                        <p class="modal-kicker">{{ $item->title }}</p>
                        <h3 class="modal-title" id="vinted-item-sold-title">Sold on Vinted</h3>
                        <div class="form-group">
                            <label for="sold_price">Sold price (€)</label>
                            <input type="number" id="sold_price" name="price" class="form-control" value="{{ old('price') }}" required min="0" max="500000" step="0.01" inputmode="decimal" autofocus>
                            <p class="form-hint">What this one piece sold for. It cost {{ format_euros($item->unitCostCents()) }} on average.</p>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm sale</button>
                        </div>
                    </form>
                </dialog>
            @endunless

            @foreach ($item->sales as $sale)
                <dialog id="vinted-sale-delete-modal-{{ $sale->id }}" class="modal" aria-labelledby="vinted-sale-delete-title-{{ $sale->id }}">
                    <form method="POST" action="{{ route('admin.marketplaces.vinted.items.sales.destroy', [$item, $sale]) }}">
                        @csrf
                        @method('DELETE')
                        <p class="modal-kicker">{{ $item->title }}</p>
                        <h3 class="modal-title" id="vinted-sale-delete-title-{{ $sale->id }}">Remove this sale?</h3>
                        <p class="modal-body">
                            The sale of {{ $sale->created_at->locale('en')->isoFormat('D MMM YYYY') }}{{ $sale->price_cents === null ? '' : ' for '.format_euros($sale->price_cents) }}
                            is removed and its piece goes back in stock.
                        </p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Remove sale</button>
                        </div>
                    </form>
                </dialog>
            @endforeach

            <dialog id="vinted-item-delete-modal" class="modal" aria-labelledby="vinted-item-delete-title">
                <form method="POST" action="{{ route('admin.marketplaces.vinted.items.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                    <p class="modal-kicker">{{ $item->title }}</p>
                    <h3 class="modal-title" id="vinted-item-delete-title">Remove this item?</h3>
                    <p class="modal-body">The item and its image are deleted. This cannot be undone.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Remove item</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection
