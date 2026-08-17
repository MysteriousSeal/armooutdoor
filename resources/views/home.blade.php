@extends('layouts.app')

@section('title', config('app.name'))
@section('meta_description', __('store.meta_home'))
@section('canonical', localized_route('home'))

@section('content')
    <div class="container home"></div>
@endsection

@push('head')
    <script type="application/ld+json">
        {{-- The key is written @@context so Blade emits a literal "@context":
             left bare, Blade compiles it as its own @context directive and the
             key is replaced by PHP, leaving the JSON-LD without @context. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => localized_route('home'),
            'inLanguage' => 'fr-FR',
            'description' => __('store.meta_home'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush
