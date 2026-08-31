@php
    // The page has already said its title, its description and its canonical
    // by the time the head renders, so the preview card reuses those rather
    // than asking every view to repeat itself. A view with a better picture,
    // or a more precise type than "a page", says so through @@section.
    $socialUrl = trim($__env->yieldContent('canonical')) ?: url()->current();
    $socialDescription = trim($__env->yieldContent('meta_description', __('store.meta_home')));
    $socialImage = trim($__env->yieldContent('og_image')) ?: asset('images/hero.webp');

    // og:site_name already carries the brand, so the suffix comes off: left
    // on, a shared link would print "Armo Outdoor" twice in the same card.
    $socialTitle = \Illuminate\Support\Str::before(
        trim($__env->yieldContent('title', config('app.name'))),
        ' — '.config('app.name'),
    );
@endphp
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $socialDescription }}">
<meta property="og:url" content="{{ $socialUrl }}">
<meta property="og:image" content="{{ $socialImage }}">
@hasSection('og_image_alt')
    <meta property="og:image:alt" content="@yield('og_image_alt')">
@endif
{{-- X falls back to the og: tags above for everything else, so the card
     format is the only thing left to declare. --}}
<meta name="twitter:card" content="summary_large_image">
