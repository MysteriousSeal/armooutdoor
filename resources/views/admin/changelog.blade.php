@extends('layouts.admin')

@section('title', 'Changelog')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Admin</p>
                    <h2 class="admin-list-title">Changelog</h2>
                    <p class="admin-list-lede">What shipped, most recent first.</p>
                </div>
                @if ($releases !== [])
                    <span class="admin-list-chip">{{ count($releases) }} {{ \Illuminate\Support\Str::plural('release', count($releases)) }}</span>
                @endif
            </div>
        </header>

        <div class="changelog-list">
            @forelse ($releases as $release)
                @php
                    try {
                        $displayDate = \Illuminate\Support\Carbon::parse($release['date'])->format('d M Y');
                    } catch (\Throwable) {
                        $displayDate = $release['date'];
                    }
                @endphp
                <article class="changelog-entry{{ $loop->first ? ' is-latest' : '' }}">
                    <div class="changelog-rail" aria-hidden="true">
                        <span class="changelog-dot"></span>
                    </div>
                    <section class="order-panel changelog-release{{ $loop->first ? ' is-latest' : '' }}">
                    <div class="changelog-release-head">
                        <div class="changelog-release-titles">
                            @if ($release['version'])
                                <span class="changelog-version">v{{ $release['version'] }}</span>
                            @endif
                            @if ($loop->first)
                                <span class="changelog-latest">Latest</span>
                            @endif
                        </div>
                        <time class="changelog-date" datetime="{{ $release['date'] }}">{{ $displayDate }}</time>
                    </div>

                    @foreach ($release['categories'] as $category)
                        <div class="changelog-category">
                            @if ($category['name'])
                                <h3 class="changelog-category-title">{{ $category['name'] }}</h3>
                            @endif
                            <ul class="changelog-items">
                                @foreach ($category['items'] as $item)
                                    <li>
                                        {!! $item['text'] !!}
                                        @if (! empty($item['children']))
                                            <ul class="changelog-items changelog-items--nested">
                                                @foreach ($item['children'] as $child)
                                                    <li>{!! $child !!}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </section>
                </article>
            @empty
                <div class="empty-state">
                    <p>No changelog entries yet.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
