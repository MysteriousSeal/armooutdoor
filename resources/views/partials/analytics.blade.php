@php
    $analyticsKey = config('services.posthog.key');
    $analyticsGa = config('services.google_analytics.id');
@endphp

{{-- Nothing is emitted where no key is configured: a shop without one loads
     no third-party script, and its Content-Security-Policy is never widened
     to allow one either. --}}
@if ($analyticsKey || $analyticsGa)
    {{-- The configuration travels as data rather than as a generated script,
         so the loader stays a static file the browser can cache and nothing
         interpolated here can become executable. --}}
    <script type="application/json" id="analytics-config">
        {!! json_encode([
            'key' => $analyticsKey,
            'host' => rtrim(config('services.posthog.host'), '/'),
            'ga' => $analyticsGa,
            'event' => $analyticsEvent ?? null,
        ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script src="{{ versioned_asset('js/analytics.js') }}" defer></script>
@endif
