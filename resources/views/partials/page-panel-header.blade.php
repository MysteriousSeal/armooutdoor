{{-- The title block for the legal and help pages.
     A panel rather than bare text, so it carries the same border and surface
     as the index beside it and the document below it, and the three read as
     one composed page instead of a heading floating above two boxes. --}}
<header class="panel-header">
    <div class="panel-header-copy">
        <p class="panel-header-kicker">{{ $kicker }}</p>
        <h1 class="panel-header-title">{{ $title }}</h1>
        @if (! empty($lede))
            <p class="panel-header-lede">{{ $lede }}</p>
        @endif
    </div>

    @if (! empty($meta))
        {{-- Set aside from the prose: it is a version marker, not a sentence
             anybody reads for meaning. --}}
        <p class="panel-header-meta">
            <span class="panel-header-meta-dot" aria-hidden="true"></span>
            {{ $meta }}
        </p>
    @endif
</header>
