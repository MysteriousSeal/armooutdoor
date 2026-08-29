@php
    /** @var array{title: string, total: int, gradient: string, slices: list<array{label: string, count: int, percent: float, color: string}>} $chart */
@endphp

<figure class="admin-donut-card">
    <figcaption class="admin-donut-title">{{ $chart['title'] }}</figcaption>

    <div class="admin-donut-body">
        <div
            class="admin-donut"
            style="background: {{ $chart['gradient'] }};"
            role="img"
            aria-label="{{ $chart['title'] }}: {{ number_format($chart['total']) }} visits"
        >
            <div class="admin-donut-hole">
                <span class="admin-donut-total">{{ number_format($chart['total']) }}</span>
                <span class="admin-donut-total-label">visits</span>
            </div>
        </div>

        <ul class="admin-donut-legend">
            @forelse ($chart['slices'] as $slice)
                <li class="admin-donut-legend-item">
                    <span class="admin-donut-swatch" style="background: {{ $slice['color'] }};" aria-hidden="true"></span>
                    <span class="admin-donut-legend-label" title="{{ $slice['label'] }}">{{ $slice['label'] }}</span>
                    <span class="admin-donut-legend-meta">
                        <span class="admin-donut-legend-percent">{{ number_format($slice['percent'], 1) }}%</span>
                        <span class="admin-donut-legend-count">{{ number_format($slice['count']) }}</span>
                    </span>
                </li>
            @empty
                <li class="admin-donut-legend-empty">No data</li>
            @endforelse
        </ul>
    </div>
</figure>
