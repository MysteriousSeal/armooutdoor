{{--
    The pencil and the bin on a hand-written line.

    Both sections show the same two buttons; only what the form is filled with
    differs, which is why the payload arrives ready-made in `$payload`.

    `$entry`   the line these act on
    `$section` sales or purchases, for the URLs
    `$payload` the values the edit form fills itself from
    `$label`   what the delete confirmation names
--}}
<button
    type="button"
    class="accounting-row-btn"
    data-modal-open="entry-modal"
    data-entry-edit
    data-entry-action="{{ route('admin.accounting.entries.update', ['section' => $section, 'month' => $monthKey, 'entry' => $entry]) }}"
    data-entry='@json($payload)'
    aria-label="Edit this entry"
    title="Edit this entry"
>
    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
        <path d="M4 20h4L19 9a2 2 0 0 0-3-3L5 17z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
    </svg>
</button>
<button
    type="button"
    class="accounting-row-btn is-danger"
    data-modal-open="entry-delete-modal"
    data-entry-delete
    data-entry-action="{{ route('admin.accounting.entries.destroy', ['section' => $section, 'month' => $monthKey, 'entry' => $entry]) }}"
    data-entry-label="{{ $label }}"
    aria-label="Delete this entry"
    title="Delete this entry"
>
    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
        <path d="M5 7h14M10 7V5h4v2m-8 0 1 13h10l1-13" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
