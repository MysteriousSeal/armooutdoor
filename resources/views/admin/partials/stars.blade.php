{{-- A rating drawn to the hundredth: five outlines with the amber layer
     clipped to the score. Five filled stars with the last one washed out read
     as a rendering fault, and could not show a 4.44 anyway. --}}
@php($starPercent = max(0.0, min(100.0, (float) $value / 5 * 100)))
<span class="admin-stars" role="img" aria-label="{{ rtrim(rtrim(number_format((float) $value, 2), '0'), '.') }} out of 5">
    <span class="admin-stars-track" aria-hidden="true">★★★★★</span>
    <span class="admin-stars-fill" style="width: {{ round($starPercent, 2) }}%" aria-hidden="true">★★★★★</span>
</span>
