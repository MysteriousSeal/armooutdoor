@extends('layouts.admin')

@section('title', $title.' — '.\App\Support\AccountingPeriods::label($period))

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">
                <a href="{{ route('admin.accounting.'.$section) }}">{{ $title }}</a>
            </p>
            <h2 class="admin-list-title">{{ \App\Support\AccountingPeriods::label($period) }}</h2>
            <p class="admin-list-lede">
                {{ $period->locale('en')->isoFormat('D MMMM') }} to {{ $period->endOfMonth()->locale('en')->isoFormat('D MMMM YYYY') }}
            </p>
        </header>

        @if ($section !== 'sales')
            <p class="empty-state">Nothing here yet.</p>
        @elseif ($orders->isEmpty())
            <p class="empty-state">No sales this month.</p>
        @else
            @php
                // Les frais retenus : la commission de la place de marché et
                // les frais d'encaissement. Le port payé de sa poche est une
                // dépense, pas une retenue sur la vente, et n'entre pas ici.
                $feesOf = fn ($order): int => ($order->marketplace_commission_cents ?? 0) + ($order->payment_fee_cents ?? 0);
                // Un remboursement reste dans le tableau — il a eu lieu — mais
                // ne s'ajoute à rien : l'argent est reparti.
                $counted = $orders->where('status', '!=', 'refunded');
                $refunded = $orders->count() - $counted->count();
                $totalCents = $counted->sum('total_cents');
                $feesCents = $counted->sum($feesOf);
            @endphp

            <div class="admin-table-wrap">
                <table class="admin-table accounting-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Channel</th>
                            <th>Type</th>
                            <th class="admin-table-num">Total</th>
                            <th class="admin-table-num">Fees</th>
                            <th class="admin-table-num">Perceived</th>
                            <th>Payment</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            @php($fees = $feesOf($order))
                            <tr class="{{ $order->status === 'refunded' ? 'is-refunded' : '' }}">
                                <td class="accounting-date">{{ $order->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">INV-{{ $order->number }}</a>
                                    @if ($order->status === 'refunded')
                                        <span class="order-chip order-chip--refunded">Refunded</span>
                                    @endif
                                </td>
                                <td>{{ $order->user?->name ?? '—' }}</td>
                                <td>{{ $order->marketplace_name ?: ($order->marketplace?->name ?? 'Direct') }}</td>
                                {{-- Tout ce qui est vendu jusqu'ici sort du stock. La colonne
                                     attend un champ sur la commande pour dire autre chose. --}}
                                <td>Stock sale</td>
                                <td class="admin-table-num">{{ format_euros($order->total_cents) }}</td>
                                <td class="admin-table-num">{{ $fees > 0 ? '−'.format_euros($fees) : '—' }}</td>
                                <td class="admin-table-num">{{ format_euros($order->total_cents - $fees) }}</td>
                                <td>Bank wire</td>
                                <td class="accounting-remark">{{ $order->number }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">
                                {{ trans_choice('{0}no sale|{1}:count sale|[2,*]:count sales', $counted->count(), ['count' => $counted->count()]) }}
                                @if ($refunded > 0)
                                    <span class="accounting-foot-note">{{ trans_choice('{1}:count refund left out|[2,*]:count refunds left out', $refunded, ['count' => $refunded]) }}</span>
                                @endif
                            </td>
                            <td class="admin-table-num">{{ format_euros($totalCents) }}</td>
                            <td class="admin-table-num">{{ $feesCents > 0 ? '−'.format_euros($feesCents) : '—' }}</td>
                            <td class="admin-table-num accounting-perceived">{{ format_euros($totalCents - $feesCents) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="accounting-note">
                Fees are the marketplace commission and the payment charge. Shipping paid out of pocket is a cost of its own and is not deducted here. Refunded orders are listed but left out of every total.
            </p>
        @endif
    </div>
@endsection
