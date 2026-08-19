<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de livraison {{ $order->number }}</title>
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
        .col-variant { width: 18%; font-size: 10.5px; color: #6b6b6b; }
        .col-sku { width: 18%; font-size: 10.5px; color: #6b6b6b; }
        .col-num { width: 12%; text-align: right; white-space: nowrap; }

        .notes-body { margin-bottom: 12px; font-size: 10.5px; line-height: 1.55; color: #6b6b6b; }
        .notes-line { margin: 0 0 3px; }
        .section-label {
            margin-bottom: 5px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .notes-count { font-size: 11.5px; }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e8e6e3;
            text-align: center;
            font-size: 9px;
            color: #6b6b6b;
        }
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
            <td><div class="title">Bon de livraison</div></td>
            <td class="title-meta">{{ $order->number }} · {{ $order->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Commande</div>
                <div class="value">{{ $order->number }}</div>
            </td>
            <td>
                <div class="label">Date de commande</div>
                <div class="value">{{ $order->created_at->translatedFormat('d F Y') }}</div>
            </td>
            <td>
                <div class="label">Transporteur</div>
                <div class="value">{{ $order->carrierName() !== '' ? $order->carrierName() : '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="addr-label">Adresse de livraison</div>
    <div class="addr-body" style="margin-bottom: 20px;">
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

    <table class="items">
        <thead>
            <tr>
                <td class="col-thumb"></td>
                <td class="col-name">Désignation</td>
                <td class="col-variant">Variante</td>
                <td class="col-sku">Article</td>
                <td class="col-num">Qté</td>
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
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-label">Nombre d'articles</div>
    <div class="notes-count">{{ $order->items->sum('quantity') }}</div>

    <div class="footer">
        Document interne — ne fait pas office de facture
    </div>
</body>
</html>
