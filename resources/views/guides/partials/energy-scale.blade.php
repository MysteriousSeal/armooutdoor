{{-- The energy scale: the whole subject on one axis.

     Logarithmic, because the three regimes the law defines span from
     eight hundredths of a joule to twenty, and a linear axis would crush
     everything an airsofter cares about into the first two per cent.

     $at   (optional) energy in joules to mark with a needle
     $caps (optional) energies to tick, unlabelled, as field limits --}}
@php
    $min = 0.08;
    $max = 100.0;
    $span = log10($max) - log10($min);

    // Where an energy sits on the axis, in per cent of its width.
    $position = fn (float $joules): float => max(0.0, min(100.0,
        (log10(max($joules, $min)) - log10($min)) / $span * 100
    ));

    $zones = [
        ['label' => 'Hors catégorie', 'class' => 'is-free', 'from' => $min, 'to' => 2.0],
        ['label' => 'Catégorie D', 'class' => 'is-d', 'from' => 2.0, 'to' => 20.0],
        ['label' => 'Catégorie C', 'class' => 'is-c', 'from' => 20.0, 'to' => $max],
    ];
@endphp

@php
    // The brace under the terrain caps: one label for the three of them,
    // because they fall within seven per cent of each other and three
    // labels would collide. Their crowding is the thing worth saying.
    $capPositions = collect($caps ?? [])->map(fn (float $cap): float => $position($cap));
@endphp

<figure
    class="glab-scale"
    data-glab-scale
    @isset($at) style="--glab-scale-at: {{ round($position($at), 2) }}%" @endisset
>
    <div class="glab-scale-marks" aria-hidden="true">
        <span style="--glab-mark-at: 0%">0,08 J</span>
        <span style="--glab-mark-at: {{ round($position(2.0), 2) }}%">2 J</span>
        <span style="--glab-mark-at: {{ round($position(20.0), 2) }}%">20 J</span>
    </div>

    <div class="glab-scale-track">
        @foreach ($zones as $zone)
            <span
                class="glab-scale-zone {{ $zone['class'] }}"
                style="--glab-zone-width: {{ round($position($zone['to']) - $position($zone['from']), 2) }}%"
                data-glab-zone="{{ $zone['class'] }}"
            ></span>
        @endforeach

        @foreach ($caps ?? [] as $cap)
            {{-- What the terrains cap at: three ticks crowded into the last
                 sliver before the legal line, which is the point. --}}
            <span class="glab-scale-cap" style="--glab-cap-at: {{ round($position($cap), 2) }}%" title="{{ number_format($cap, 2, ',', ' ') }} joule"></span>
        @endforeach

        @isset($at)
            <span class="glab-scale-needle" data-glab-needle></span>
        @endisset
    </div>

    @if ($capPositions->isNotEmpty())
        <div
            class="glab-scale-caps"
            style="--glab-caps-from: {{ round($capPositions->min(), 2) }}%; --glab-caps-to: {{ round($capPositions->max(), 2) }}%; --glab-caps-mid: {{ round(($capPositions->min() + $capPositions->max()) / 2, 2) }}%"
        >
            <span class="glab-scale-caps-brace" aria-hidden="true"></span>
            <span class="glab-scale-caps-label">Limites de terrain</span>
        </div>
    @endif

    <figcaption class="glab-scale-legend">
        @foreach ($zones as $zone)
            <span
                class="glab-scale-name {{ $zone['class'] }}"
                style="--glab-zone-width: {{ round($position($zone['to']) - $position($zone['from']), 2) }}%"
                data-glab-name="{{ $zone['class'] }}"
            >{{ $zone['label'] }}</span>
        @endforeach
    </figcaption>
</figure>
