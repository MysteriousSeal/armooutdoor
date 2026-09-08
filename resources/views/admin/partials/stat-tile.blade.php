{{-- $label · $value · $delta · $upIsGood · $points (optionnel)

     L'écart lui-même est rendu par admin.partials.delta : la ligne de compte
     affiche le même signe hors d'une tuile, et deux copies de la règle
     « monter n'est pas toujours une bonne nouvelle » finiraient par
     diverger. --}}
<div class="dash-tile">
    <span class="dash-tile-label">{{ $label }}</span>
    <span class="dash-tile-value">{{ $value }}</span>
    <span class="dash-tile-foot">
        @include('admin.partials.delta', ['delta' => $delta, 'upIsGood' => $upIsGood ?? true])
        <span class="dash-tile-compare">{{ $comparison }}</span>
        @isset($points)
            @include('admin.partials.sparkline', ['points' => $points, 'tone' => 'accent'])
        @endisset
    </span>
</div>
