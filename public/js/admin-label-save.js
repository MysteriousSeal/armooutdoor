// Saving a label's wording without leaving the list.
//
// The forms work on their own: each is a real POST that reloads the page on the
// same tab and search. This intercepts them where the browser can do better,
// keeping the page still and confirming with the toast the rest of the admin
// uses.
//
// A save can also make an article printable, so the answer names the articles
// that can be printed now and their buttons are switched on. Otherwise the row
// would go on claiming there is something missing.
(function () {
    'use strict';

    var forms = Array.prototype.slice.call(document.querySelectorAll('[data-label-form]'));
    var token = document.querySelector('meta[name="csrf-token"]');

    if (forms.length === 0 || !token || !window.fetch) {
        return;
    }

    function toast(text) {
        if (window.armoToast) {
            window.armoToast.show(text);
        }
    }

    /** Clears the messages left under the fields by an earlier attempt. */
    function clearErrors(form) {
        form.querySelectorAll('[data-label-error]').forEach(function (el) {
            el.remove();
        });
    }

    function showError(form, field, message) {
        var input = form.elements[field];

        if (!input) {
            return;
        }

        var p = document.createElement('p');
        p.className = 'form-error';
        p.setAttribute('data-label-error', '');
        p.textContent = message;
        input.insertAdjacentElement('afterend', p);
    }

    /**
     * Switches the buttons of each article on or off as the save left it, and
     * its status with them.
     *
     * A plain product answers with an empty name, a variant with its id, which
     * is how the cells identify themselves. A button with no link to carry, the
     * received batch of an article never received, stays off either way.
     */
    function switchOn(productId, printable) {
        document.querySelectorAll('[data-label-action][data-product="' + productId + '"]').forEach(function (cell) {
            var isPrintable = printable.indexOf(cell.getAttribute('data-variant')) !== -1;
            var status = cell.querySelector('[data-label-status]');

            if (status) {
                status.className = 'label-status ' + (isPrintable ? 'is-ready' : 'is-incomplete');
                status.textContent = isPrintable ? 'Ready' : 'Incomplete';
            }

            cell.querySelectorAll('[data-label-button]').forEach(function (old) {
                var href = old.getAttribute('data-href');
                var live = isPrintable && href !== '';

                if ((old.tagName === 'A') === live) {
                    return;
                }

                var button = document.createElement(live ? 'a' : 'span');
                button.className = 'btn btn-secondary btn-small' + (live ? '' : ' is-disabled');
                button.textContent = old.textContent;
                button.title = old.title;
                button.setAttribute('data-label-button', '');
                button.setAttribute('data-href', href);

                if (live) {
                    button.href = href;
                } else {
                    button.setAttribute('aria-disabled', 'true');
                }

                old.replaceWith(button);
            });
        });
    }

    /**
     * Marks a form as edited, or as settled again.
     *
     * A row half filled in and left alone is the easy mistake on a long list;
     * the accent edge and the button say which rows are waiting.
     */
    function markDirty(form, dirty) {
        form.classList.toggle('is-dirty', dirty);
    }

    forms.forEach(function (form) {
        form.addEventListener('input', function () {
            markDirty(form, true);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var submit = form.querySelector('[type="submit"]');
            var body = new FormData(form);

            clearErrors(form);

            if (submit) {
                submit.disabled = true;
            }

            fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token.getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body,
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    });
                })
                .then(function (result) {
                    if (result.ok) {
                        toast(result.payload.message || 'Label wording saved.');
                        markDirty(form, false);
                        switchOn(form.getAttribute('data-product'), result.payload.printable || []);

                        return;
                    }

                    // 422: the messages go back under their fields, as they
                    // would after a round trip to the server.
                    if (result.status === 422 && result.payload.errors) {
                        Object.keys(result.payload.errors).forEach(function (field) {
                            showError(form, field, result.payload.errors[field][0]);
                        });
                        toast('Wording not saved — check the fields.');

                        return;
                    }

                    toast('Wording not saved.');
                })
                .catch(function () {
                    // The network is gone, so say so rather than leaving the
                    // row looking as though it saved.
                    toast('Wording not saved.');
                })
                .then(function () {
                    if (submit) {
                        submit.disabled = false;
                    }
                });
        });
    });
})();
