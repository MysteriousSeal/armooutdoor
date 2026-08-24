{{-- Douze points décoratifs : ni axe, ni légende, ni infobulle. Écrit à la
     main en SVG plutôt que confié à Chart.js — quatre instances de plus pour
     quatre courbes muettes, ce serait du poids et un premier rendu plus lent.

     $points : array<int> · $tone : 'accent'|'muted' --}}
@php
    $values = array_values($points ?? []);
    $count = count($values);
    $width = 72;
    $height = 24;
    $pad = 2;
    $min = $count > 0 ? min($values) : 0;
    $max = $count > 0 ? max($values) : 0;
    $span = max(1, $max - $min);

    $coords = [];
    foreach ($values as $index => $value) {
        $x = $count > 1 ? $pad + ($index / ($count - 1)) * ($width - 2 * $pad) : $width / 2;
        $y = $height - $pad - (($value - $min) / $span) * ($height - 2 * $pad);
        $coords[] = round($x, 2).','.round($y, 2);
    }
@endphp

@if ($count > 1)
    <svg class="spark spark--{{ $tone ?? 'accent' }}" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" aria-hidden="true" focusable="false">
        <polyline points="{{ implode(' ', $coords) }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></polyline>
    </svg>
@endif
