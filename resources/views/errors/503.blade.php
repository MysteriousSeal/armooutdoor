<!DOCTYPE html>
@php
    $theme = \App\Support\ThemePreference::resolve(request());
@endphp
<html lang="fr" data-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|; )theme=(light|dark)/);
            if (match) {
                document.documentElement.setAttribute('data-theme', match[1]);
            }
        })();
    </script>
    <title>Maintenance en cours — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#8b7e74">
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ versioned_asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/errors.css') }}">
</head>
<body>
    <div class="container">
        <section class="error-page">
            <p class="error-503-pulse" aria-hidden="true">
                <span></span><span></span><span></span>
            </p>
            <p class="error-page-code" aria-hidden="true">503</p>
            <h1 class="error-page-title">Le site fait peau neuve</h1>
            <p class="error-page-lede">
                Une mise à jour est en cours. Ça ne prendra que quelques instants — revenez juste après.
            </p>

            <div class="error-page-actions">
                <a href="{{ url()->current() }}" class="btn btn-primary">Réessayer</a>
            </div>
        </section>
    </div>
</body>
</html>
