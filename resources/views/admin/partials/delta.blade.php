{{-- $delta · $upIsGood

     L'écart contre la tranche précédente, seul. La tuile le composait
     elle-même ; la ligne de compte a besoin du même signe, de la même
     couleur et de la même règle sans reprendre la tuile autour. --}}
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

@if ($percent === null)
    {{-- No reference to measure against: growth from zero has no
         percentage, and "+∞ %" tells nobody anything. --}}
    <span class="dash-delta is-flat">—</span>
@else
    <span class="dash-delta is-{{ $tone }}">
        <span aria-hidden="true">{{ $arrow }}</span>{{ $percent > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%
    </span>
@endif
