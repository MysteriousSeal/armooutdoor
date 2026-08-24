{{-- $label · $value · $delta · $upIsGood · $points (optionnel)

     La couleur de l'écart, c'est le sens du mouvement croisé avec le fait
     qu'aller vers le haut soit une bonne nouvelle : des remboursements qui
     montent ne sont pas verts. --}}
@php
    $direction = $delta['direction'] ?? 'flat';
    $percent = $delta['percent'] ?? null;
    $good = $upIsGood ?? true;

    $tone = match (true) {
        $direction === 'flat' => 'flat',
        ($direction === 'up') === $good => 'good',
        default => 'bad',
    };

    $arrow = match ($direction) {
        'up' => '▲',
        'down' => '▼',
        default => '',
    };
@endphp

<div class="dash-tile">
    <span class="dash-tile-label">{{ $label }}</span>
    <span class="dash-tile-value">{{ $value }}</span>
    <span class="dash-tile-foot">
        @if ($percent === null)
            {{-- Pas de référent : une croissance depuis zéro n'a pas de
                 pourcentage, et « +∞ % » n'informe personne. --}}
            <span class="dash-delta is-flat">—</span>
        @else
            <span class="dash-delta is-{{ $tone }}">
                <span aria-hidden="true">{{ $arrow }}</span>{{ $percent > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%
            </span>
        @endif
        <span class="dash-tile-compare">{{ $comparison }}</span>
        @isset($points)
            @include('admin.partials.sparkline', ['points' => $points, 'tone' => 'accent'])
        @endisset
    </span>
</div>
