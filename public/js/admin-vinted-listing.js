(function () {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.vinted-copy'));

    // The character count and the names of the picked files live here too:
    // they have nothing to do with the clipboard, and must work even where
    // it is missing.
    countCharacters();
    reportPickedFiles();
    wireGenerator();

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

    /**
     * The « Write with Claude » button: it asks the server for a title and a
     * description, and writes them into the fields as though they had been
     * typed. Nothing is saved — the form still has to be submitted, which is
     * what makes the proposal correctable rather than applied.
     */
    function wireGenerator() {
        var run = document.querySelector('.vinted-assist-run');
        var status = document.querySelector('[data-generate-status]');
        var title = document.getElementById('vinted-title');
        var description = document.getElementById('vinted-description');
        var price = document.getElementById('vinted-price');

        if (!run || !title || !description) {
            return;
        }

        var idle = run.textContent;
        var token = document.querySelector('meta[name="csrf-token"]');

        function say(message, failed) {
            if (!status) {
                return;
            }

            status.hidden = !message;
            status.textContent = message || '';
            status.className = 'vinted-assist-status' + (failed ? ' is-failed' : '');
        }

        // The counter under each field listens for `input`; a value written
        // by script raises no event of its own, and the count would keep the
        // figure from before.
        function fill(field, value) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        run.addEventListener('click', function () {
            // What is already written was written by somebody: it is not
            // replaced without being asked for.
            var written = title.value || description.value || (price && price.value);

            if (written && !window.confirm('Replace what is already written?')) {
                return;
            }

            run.disabled = true;
            run.textContent = 'Writing…';
            say('Claude is reading the product sheet — a few seconds.', false);

            fetch(run.getAttribute('data-generate-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw new Error(body && body.message ? body.message : 'Claude could not be reached.');
                    }

                    return body;
                });
            }).then(function (body) {
                fill(title, body.title || '');
                fill(description, body.description || '');

                // A price can come back absent — the two fields above cannot.
                // The field keeps what it had rather than being emptied, and
                // the strip says so: a field silently skipped reads as a bug.
                var suggested = body.price === null || body.price === undefined
                    ? NaN
                    : parseFloat(body.price);

                if (price && !isNaN(suggested)) {
                    fill(price, String(suggested));
                }

                say(
                    isNaN(suggested)
                        ? 'Written, but Claude suggested no price — set it yourself.'
                        : 'Written. The price leaves room to be haggled down — read it over, then save.',
                    isNaN(suggested)
                );
            }).catch(function (error) {
                say(error.message || 'Claude could not be reached.', true);
            }).then(function () {
                run.disabled = false;
                run.textContent = idle;
            });
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