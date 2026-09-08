(function () {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.vinted-copy'));

    // The character count and the names of the picked files live here too:
    // they have nothing to do with the clipboard, and must work even where
    // it is missing.
    countCharacters();
    reportPickedFiles();

    if (buttons.length === 0) {
        return;
    }

    // With no clipboard — an old browser, a page served without HTTPS — the
    // buttons would do nothing, silently. Better that they are not there at
    // all: the field is right beside them and selects by hand.
    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
        buttons.forEach(function (button) {
            button.hidden = true;
        });

        return;
    }

    function label(button, text) {
        button.textContent = text;
    }

    buttons.forEach(function (button) {
        var idle = button.textContent;
        var timer = null;

        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-copy-from'));

            if (!field) {
                return;
            }

            var value = field.value;

            // Vinted's price field wants a comma and refuses a full stop.
            if (button.hasAttribute('data-copy-decimal-comma')) {
                value = value.replace('.', ',');
            }

            navigator.clipboard.writeText(value).then(function () {
                clearTimeout(timer);
                button.classList.add('is-copied');
                label(button, 'Copied');

                timer = setTimeout(function () {
                    button.classList.remove('is-copied');
                    label(button, idle);
                }, 1600);
            }, function () {
                // A refusal from the browser is said out loud: a button that
                // does nothing lets one believe the value went across.
                clearTimeout(timer);
                label(button, 'Press ⌘C');

                timer = setTimeout(function () {
                    label(button, idle);
                }, 2400);

                field.focus();
                field.select();
            });
        });
    });

    /**
     * How many characters are written, and — for the title — from where
     * Vinted will cut on a phone. Information, never a block: a longer title
     * is still a valid title.
     */
    function countCharacters() {
        var fields = Array.prototype.slice.call(document.querySelectorAll('[data-counter-for]'));

        fields.forEach(function (field) {
            var target = document.getElementById(field.getAttribute('data-counter-for'));

            if (!target) {
                return;
            }

            var ideal = parseInt(field.getAttribute('data-counter-ideal'), 10);

            function render() {
                var length = field.value.length;

                if (isNaN(ideal)) {
                    target.textContent = length + (length === 1 ? ' character' : ' characters');
                    target.className = '';

                    return;
                }

                target.textContent = length + ' / ' + ideal + ' before Vinted trims it';
                target.className = length > ideal ? 'is-over' : '';
            }

            field.addEventListener('input', render);
            render();
        });
    }

    /** What the file picker kept, said in words. */
    function reportPickedFiles() {
        var notes = Array.prototype.slice.call(document.querySelectorAll('[data-picked-for]'));

        notes.forEach(function (note) {
            var input = document.getElementById(note.getAttribute('data-picked-for'));

            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                var count = input.files ? input.files.length : 0;

                note.hidden = count === 0;
                note.textContent = count === 1
                    ? '1 photo ready to upload — save to keep it'
                    : count + ' photos ready to upload — save to keep them';
            });
        });
    }
})();