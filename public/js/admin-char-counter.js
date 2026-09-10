(function () {
    // A soft counter under a field: counts live, turns to a warning past
    // the advised limit, never blocks input. Wire a field with
    // data-char-counter="<element id>" and data-char-limit="<n>".
    // The three notes default to the admin SEO-preview copy but can be
    // overridden per field with data-char-note-good/-short/-over.
    document.querySelectorAll('[data-char-counter]').forEach(function (input) {
        var counter = document.getElementById(input.getAttribute('data-char-counter'));
        var limit = parseInt(input.getAttribute('data-char-limit'), 10);

        if (!counter || !limit) {
            return;
        }

        var min = parseInt(input.getAttribute('data-char-min') || '0', 10);
        var noteGood = input.getAttribute('data-char-note-good');
        var noteShort = input.getAttribute('data-char-note-short');
        var noteOver = input.getAttribute('data-char-note-over');

        if (noteGood === null) noteGood = ' — reads well in a search result';
        if (noteShort === null) noteShort = ' — a bit short to say much in a search result';
        if (noteOver === null) noteOver = ' — longer than a search result shows';

        function update() {
            var length = input.value.length;
            var note = length > limit ? noteOver : (length < min ? noteShort : noteGood);

            counter.hidden = false;
            counter.textContent = length + ' / ' + limit + note;
            // Grey while short of data-char-min, green inside the good
            // range, red past the limit.
            counter.classList.toggle('is-good', length >= min && length <= limit);
            counter.classList.toggle('is-over', length > limit);
        }

        input.addEventListener('input', update);
        update();
    });
})();
