(function () {
    var viewport = document.getElementById('conversation-viewport');

    if (!viewport) {
        return;
    }

    // A conversation opens where it lives: at its latest message.
    viewport.scrollTop = viewport.scrollHeight;

    /* --- Composer comfort ----------------------------------------------- */

    var form = document.getElementById('conversation-reply-form');
    var textarea = form ? form.querySelector('textarea[name="body"]') : null;
    var count = document.getElementById('composer-count');

    if (textarea) {
        var MAX = parseInt(textarea.getAttribute('maxlength') || '5000', 10);

        var fit = function () {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 240) + 'px';

            // The counter only speaks up once the limit is actually near.
            if (count) {
                var left = MAX - textarea.value.length;
                count.hidden = left > 500;
                count.textContent = left + ' left';
            }
        };

        textarea.addEventListener('input', fit);
        fit();

        textarea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    }

    /* --- Close / reopen without leaving --------------------------------- */

    function setClosed(closed) {
        var chip = document.getElementById('conversation-closed-chip');
        var note = document.getElementById('conversation-closed-note');

        if (chip) {
            chip.hidden = !closed;
        }
        if (note) {
            note.hidden = !closed;
        }
        if (form) {
            form.hidden = closed;
        }

        document.querySelectorAll('[data-status-form="close"]').forEach(function (el) {
            el.hidden = closed;
        });
        document.querySelectorAll('[data-status-form="reopen"]').forEach(function (el) {
            // The note's reopen form lives inside the note and follows it;
            // only the header's standalone one needs its own visibility.
            if (!note || !note.contains(el)) {
                el.hidden = !closed;
            }
        });
    }

    document.querySelectorAll('[data-status-form]').forEach(function (statusForm) {
        statusForm.addEventListener('submit', function (event) {
            if (statusForm.hasAttribute('data-bypass')) {
                return;
            }

            event.preventDefault();

            var button = statusForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }

            fetch(statusForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': statusForm.querySelector('input[name="_token"]').value,
                    Accept: 'application/json',
                },
                body: new FormData(statusForm),
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    setClosed(data.status === 'closed');
                    if (window.armoToast) {
                        window.armoToast.show(data.message);
                    }
                })
                .catch(function () {
                    statusForm.setAttribute('data-bypass', '1');
                    statusForm.submit();
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                    }
                });
        });
    });
})();
