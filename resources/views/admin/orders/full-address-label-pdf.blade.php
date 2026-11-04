<!DOCTYPE html>
{{--
    A 15 × 10 cm address label (landscape) for a Lettre suivie envelope: the
    sender in the top left corner, the recipient in the bottom right one,
    the sender under a small heading so the two never get mixed up, and the
    order reference above it to match the envelope with its order. No
    phone, no price.
--}}
@php
    $address = $order->address_snapshot;

    $recipient = array_values(array_filter([
        format_person_name($address['first_name'] ?? null, $address['last_name'] ?? null),
        $address['line1'] ?? null,
        $address['line2'] ?? null,
        trim(($address['postal_code'] ?? '').' '.($address['city'] ?? '')),
        filled($address['country'] ?? null) ? __('store.country_'.$address['country']) : null,
    ], fn ($line) => filled($line)));

    $sender = [$company->value('company_name'), ...$company->addressLines(), __('store.country_FR')];
@endphp
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a1a;
        }

        .block {
            position: absolute;
            line-height: 1.4;
        }

        /* Both blocks share one size, so neither reads as the main one. */
        .sender {
            top: 18pt;
            left: 18pt;
            font-size: 9pt;
        }

        .recipient {
            bottom: 24pt;
            right: 28pt;
            max-width: 265pt;
            font-size: 9pt;
        }

        .heading {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 3pt;
        }

        .reference {
            font-size: 8pt;
            margin-bottom: 8pt;
        }

        .name {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="block sender">
        <div class="reference">Réf. commande : {{ $order->number }}</div>
        <div class="heading">Expéditeur</div>
        @foreach ($sender as $line)
            <div @if ($loop->first) class="name" @endif>{{ $line }}</div>
        @endforeach
    </div>

    <div class="block recipient">
        @foreach ($recipient as $line)
            <div @if ($loop->first) class="name" @endif>{{ $line }}</div>
        @endforeach
    </div>
</body>
</html>
