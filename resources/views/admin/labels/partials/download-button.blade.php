{{-- A download button on the labels page, live or greyed. data-href keeps
     the link either way, empty when there is nothing it could download. --}}
@if ($live)
    <a href="{{ $href }}" class="btn btn-secondary btn-small" data-label-button data-href="{{ $href }}" title="{{ $title }}">{{ $text }}</a>
@else
    <span class="btn btn-secondary btn-small is-disabled" aria-disabled="true" data-label-button data-href="{{ $href }}" title="{{ $title }}">{{ $text }}</span>
@endif
