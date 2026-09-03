<!DOCTYPE html>
{{--
    A 70 × 50 mm address label for a Lettre suivie envelope: the recipient
    and nothing else. The block sits centred on the card, its lines left
    aligned against each other the way a postal address reads. The font
    size steps down a little for long lines, and past the 11 pt floor a
    long street name wraps onto a second line instead of shrinking the
    label into small print.
--}}
@php
    $address = $order->address_snapshot;

    $lines = array_values(array_filter([
        format_person_name($address['first_name'] ?? null, $address['last_name'] ?? null),
        $address['line1'] ?? null,
        $address['line2'] ?? null,
        trim(($address['postal_code'] ?? '').' '.($address['city'] ?? '')),
        filled($address['country'] ?? null) ? __('store.country_'.$address['country']) : null,
    ], fn ($line) => filled($line)));

    // The card is 198 pt wide (70 mm); the block keeps 160 of them after
    // the edge padding. A DejaVu Sans character advances by about 0.55 em
    // on average, so the size follows the longest line — but only down to
    // 11 pt: past that, a long street name wraps onto a second line rather
    // than shrinking the whole label into small print.
    $longest = max(1, ...array_map('mb_strlen', $lines));
    $size = (int) min(14, max(11, floor(160 / ($longest * 0.55))));
@endphp
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
        }

        .card {
            width: 100%;
            height: 141pt; /* 50 mm */
            /* Horizontal only: the td's height is the whole card, and
               vertical padding on top of it would spill onto a second
               page. Centring already keeps the block off the edges. */
            padding: 0 16pt;
            text-align: center;
            vertical-align: middle;
        }

        .address {
            display: inline-block;
            text-align: left;
            font-size: {{ $size }}pt;
            line-height: 1.45;
            color: #1a1a1a;
        }

        .address .name {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="card">
                <div class="address">
                    @foreach ($lines as $line)
                        <div @if ($loop->first) class="name" @endif>{{ $line }}</div>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
