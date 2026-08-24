@extends('layouts.admin')

@section('title', 'Average purchase cost — '.$product->localizedName())

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div class="stock-history-heading">
                    <img class="admin-product-thumb" src="{{ $product->thumbnailUrl() }}" alt="" loading="lazy">
                    <div>
                        <p class="admin-list-kicker">Inventory</p>
                        <h2 class="admin-list-title">{{ $product->localizedName() }}</h2>
                        <p class="admin-list-lede">How the average purchase cost, incl. VAT, was worked out — each order's discount and additional costs prorated by quantity across everything it delivered.</p>
                    </div>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Back to product</a>
                </div>
            </div>
        </header>

        @if ($lines->isEmpty())
            <p class="empty-state">No purchase order has been received for this product yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Purchase order</th>
                            <th class="admin-table-num">Qty received</th>
                            <th class="admin-table-num">Unit cost<span class="po-col-note">excl. VAT</span></th>
                            <th class="admin-table-num">VAT rate</th>
                            <th class="admin-table-num">Unit cost<span class="po-col-note">incl. VAT</span></th>
                            <th class="admin-table-num">
                                Charges share
                                <span class="po-col-note">incl. VAT</span>
                                <span class="average-cost-help" title="This order's discount and additional costs, split by how much of the order's received quantity this line accounts for.">?</span>
                            </th>
                            <th class="admin-table-num">Line total<span class="po-col-note">incl. VAT</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php
                                $po = $line->purchaseOrder;
                                $unitInclVatCents = $po->withVatCents($line->unit_cost_cents);
                                $chargesShareCents = $po->withVatCents($po->lineShareOfChargesCents($line));
                                $lineTotalInclVatCents = $po->receivedLineTotalInclVatWithChargesCents($line);
                            @endphp
                            <tr>
                                <td>{{ ($po->received_at ?? $po->created_at)->format('d/m/Y') }}</td>
                                <td><a href="{{ route('admin.purchase-orders.show', $po) }}" class="admin-table-strong">{{ $po->number }}</a></td>
                                <td class="admin-table-num">{{ number_format($line->quantity_received) }}</td>
                                <td class="admin-table-num">{{ format_euros($line->unit_cost_cents) }}</td>
                                <td class="admin-table-num">{{ rtrim(rtrim(number_format($po->vatRatePercent(), 1), '0'), '.') }}%</td>
                                <td class="admin-table-num">{{ format_euros($unitInclVatCents) }}</td>
                                <td class="admin-table-num {{ $chargesShareCents < 0 ? 'is-charge-negative' : ($chargesShareCents > 0 ? 'is-charge-positive' : '') }}">
                                    {{ $chargesShareCents > 0 ? '+' : '' }}{{ format_euros($chargesShareCents) }}
                                </td>
                                <td class="admin-table-num">{{ format_euros($lineTotalInclVatCents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="average-cost-total-row">
                            <td colspan="2">Total</td>
                            <td class="admin-table-num">{{ number_format($units) }}</td>
                            <td colspan="4"></td>
                            <td class="admin-table-num">{{ format_euros($totalInclVatCents) }}</td>
                        </tr>
                        <tr class="average-cost-avg-row">
                            <td colspan="7">Average, incl. VAT — total ÷ units received</td>
                            <td class="admin-table-num">{{ format_euros($averageCostInclVatCents) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endsection
