{{-- Our product's name and long description, copied to the clipboard. The
     description goes as formatted HTML with a plain-text fallback, so
     paragraphs and lists survive a paste into a rich editor. --}}
<span class="nb-copy">
    <button type="button" class="btn btn-secondary btn-small" data-copy-text="{{ $name }}">Copy title</button>
    @if (trim($descriptionText) !== '')
        <button
            type="button"
            class="btn btn-secondary btn-small"
            data-copy-text="{{ $descriptionText }}"
            data-copy-html="{{ $description }}"
        >Copy description</button>
    @endif
</span>
