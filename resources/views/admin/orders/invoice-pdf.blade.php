<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $order->number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11.5px;
            line-height: 1.45;
            color: #2c2c2c;
            padding: 48px 56px;
        }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .header td { vertical-align: top; }
        .brand { font-size: 20.5px; letter-spacing: -0.03em; }
        .brand-primary { font-weight: bold; color: #2c2c2c; }
        .brand-secondary { font-weight: normal; color: #6b6b6b; }
        .brand-tag {
            margin-top: 4px;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .company-info { text-align: right; font-size: 10.5px; line-height: 1.55; color: #6b6b6b; }
        .company-info .name { font-weight: bold; color: #2c2c2c; }

        .title-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .title-row td { vertical-align: bottom; }
        .title {
            font-size: 20.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #2c2c2c;
        }
        .title-meta { text-align: right; font-size: 10.5px; color: #6b6b6b; }

        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.meta td {
            width: 33.33%;
            padding: 8px 10px;
            background: #f7f6f4;
            border: 1px solid #e8e6e3;
        }
        table.meta td + td { border-left: 0; }
        table.meta .label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        table.meta .value { padding-top: 3px; font-size: 11.5px; color: #2c2c2c; }

        table.addresses { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.addresses td { width: 50%; vertical-align: top; padding-right: 18px; }
        .addr-label {
            margin-bottom: 6px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .addr-body { font-size: 11.5px; line-height: 1.55; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items thead td {
            padding: 7px 5px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
            border-bottom: 1px solid #8b7e74;
        }
        table.items tbody td {
            padding: 9px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #e8e6e3;
        }
        .col-thumb { width: 42px; }
        .col-thumb img { width: 36px; height: 36px; }
        .col-name { font-size: 11.5px; }
        .col-variant { width: 15%; font-size: 10.5px; color: #6b6b6b; }
        .col-sku { width: 15%; font-size: 10.5px; color: #6b6b6b; }
        .col-num { width: 10%; text-align: right; white-space: nowrap; }

        .bottom { width: 100%; border-collapse: collapse; }
        .bottom td { vertical-align: top; }
        .notes-col { width: 58%; padding-right: 22px; }
        .totals-col { width: 42%; }
        .section-label {
            margin-bottom: 5px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .notes-body { margin-bottom: 12px; font-size: 10.5px; line-height: 1.55; color: #6b6b6b; }
        .notes-line { margin: 0 0 3px; }
        .notes-count { font-size: 11.5px; }

        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 6px 0; font-size: 11.5px; }
        table.totals .t-label { color: #6b6b6b; }
        table.totals .t-value { text-align: right; white-space: nowrap; }
        table.totals tr.grand td {
            padding-top: 8px;
            border-top: 1px solid #8b7e74;
            font-size: 14px;
            font-weight: bold;
            color: #2c2c2c;
        }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e8e6e3;
            text-align: center;
            font-size: 9px;
            color: #6b6b6b;
        }
        .footer-link { margin-top: 4px; }
        .footer-link a { color: #6b6b6b; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">
                    <span class="brand-primary">Armo</span><span class="brand-secondary">Outdoor</span>
                </div>
                <div class="brand-tag">Stand et terrain</div>
            </td>
            <td class="company-info">
                <div class="name">{{ $company->value('company_name') }}</div>
                @foreach ($company->addressLines() as $line)
                    <div>{{ $line }}</div>
                @endforeach
                <div>SIRET {{ $company->value('siret') }}</div>
                <div>{{ $company->formattedPhone() }}</div>
                <div>{{ $company->value('contact_email') }}</div>
            </td>
        </tr>
    </table>

    <table class="title-row">
        <tr>
            <td><div class="title">Facture</div></td>
            <td class="title-meta">INV-{{ $order->number }} · {{ $order->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Commande</div>
                <div class="value">{{ $order->number }}</div>
            </td>
            <td>
                <div class="label">N° de facture</div>
                <div class="value">INV-{{ $order->number }}</div>
            </td>
            <td>
                <div class="label">Date de commande</div>
                <div class="value">{{ $order->created_at->translatedFormat('d F Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="addresses">
        <tr>
            <td>
                <div class="addr-label">Adresse d'expédition</div>
                <div class="addr-body">
                    {{ format_person_name($order->address_snapshot['first_name'], $order->address_snapshot['last_name']) }}<br>
                    @if ($order->relay_snapshot)
                        {{ $order->relay_snapshot['name'] }}<br>
                        {{ $order->relay_snapshot['line1'] }}<br>
                        {{ $order->relay_snapshot['postal_code'] }} {{ $order->relay_snapshot['city'] }}<br>
                    @else
                        {{ $order->address_snapshot['line1'] }}<br>
                        @if (! empty($order->address_snapshot['line2']))
                            {{ $order->address_snapshot['line2'] }}<br>
                        @endif
                        {{ $order->address_snapshot['postal_code'] }} {{ $order->address_snapshot['city'] }}<br>
                    @endif
                    {{ __('store.country_'.$order->address_snapshot['country']) }}
                </div>
            </td>
            <td>
                <div class="addr-label">Adresse de facturation</div>
                <div class="addr-body">
                    @php($billing = $order->billing_address_snapshot ?? $order->address_snapshot)
                    {{ format_person_name($billing['first_name'], $billing['last_name']) }}<br>
                    {{ $billing['line1'] }}<br>
                    @if (! empty($billing['line2']))
                        {{ $billing['line2'] }}<br>
                    @endif
                    {{ $billing['postal_code'] }} {{ $billing['city'] }}<br>
                    {{ __('store.country_'.$billing['country']) }}
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <td class="col-thumb"></td>
                <td class="col-name">Désignation</td>
                <td class="col-variant">Variante</td>
                <td class="col-sku">Article</td>
                <td class="col-num">Qté</td>
                <td class="col-num">Prix</td>
                <td class="col-num">Total</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="col-thumb">
                        @if ($item->image)
                            <img src="{{ $item->imagePath() }}" alt="">
                        @endif
                    </td>
                    <td class="col-name">{{ $item->localizedName() }}</td>
                    <td class="col-variant">{{ $item->variant_label ?: '-' }}</td>
                    <td class="col-sku">{{ $item->resolvedSku() ?? '—' }}</td>
                    <td class="col-num">× {{ $item->quantity }}</td>
                    <td class="col-num">{{ format_euros($item->unit_price_cents) }}</td>
                    <td class="col-num">{{ $item->formattedLineTotal() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="bottom">
        <tr>
            <td class="notes-col">
                <div class="section-label">Notes</div>
                <div class="notes-body">
                    @if (! ($order->is_manual && ($order->marketplace_id || $order->marketplace_name)) && $order->payment_method)
                        <div class="notes-line">Paiement : {{ $order->payment_method->label() }}</div>
                    @endif
                    @if ($order->carrierName() !== '')
                        <div class="notes-line">Transporteur : {{ $order->carrierName() }}</div>
                    @endif
                    @if ($order->hasTracking())
                        <div class="notes-line">Suivi : {{ $order->tracking_number }}</div>
                    @endif
                    @if ($order->package_type_name)
                        <div class="notes-line">Colis : {{ $order->package_type_name }}</div>
                    @endif
                    @if ($order->marketplace_name)
                        <div class="notes-line">Paiement sur {{ $order->marketplace_name }}</div>
                    @endif
                    @if ($order->invoiceNote())
                        <div class="notes-line">{{ $order->invoiceNote() }}</div>
                    @endif
                </div>
                <div class="section-label">Nombre d'articles</div>
                <div class="notes-count">{{ $order->items->sum('quantity') }}</div>
            </td>
            <td class="totals-col">
                <table class="totals">
                    <tr>
                        <td class="t-label">Sous-total</td>
                        <td class="t-value">{{ $order->formattedSubtotal() }}</td>
                    </tr>
                    {{-- Only when something actually came off the goods: a
                         shipping code takes nothing off them, and would
                         otherwise print "Réduction -0,00 €". --}}
                    @if ($order->discount_cents > 0)
                        <tr>
                            <td class="t-label">Réduction</td>
                            <td class="t-value">-{{ $order->formattedDiscountCents() }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="t-label">Livraison</td>
                        <td class="t-value">{{ $order->formattedShipping() }}</td>
                    </tr>
                    {{-- Without this the invoice does not add up: Livraison
                         shows the carrier's real price, but the customer was
                         never charged it. --}}
                    @if ($order->shipping_discount_cents > 0)
                        <tr>
                            <td class="t-label">{{ __('store.checkout_shipping_discount') }}</td>
                            <td class="t-value">-{{ format_euros($order->shipping_discount_cents) }}</td>
                        </tr>
                    @endif
                    @if ($order->status === 'refunded')
                        <tr>
                            <td class="t-label">Remboursement</td>
                            <td class="t-value">{{ format_euros(-$order->total_cents) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td class="t-label">Total TTC</td>
                        <td class="t-value">{{ $order->status === 'refunded' ? format_euros(0) : $order->formattedTotal() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        TVA non applicable - art. L. 223-3 du Code des impositions sur les biens et services (CIBS)
        @if ($company->invoice_footer_enabled && ($company->invoice_footer_url || $company->invoice_footer_text))
            <div class="footer-link">
                @if ($company->invoice_footer_text)
                    <div>{{ $company->invoice_footer_text }}</div>
                @endif
                @if ($company->invoice_footer_url)
                    <div><a href="{{ $company->invoice_footer_url }}">{{ $company->invoice_footer_url }}</a></div>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
