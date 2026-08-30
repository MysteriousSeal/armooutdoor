(function () {
    var form = document.querySelector('.admin-customer-reset-form');

    if (!form) {
        return;
    }

    var button = form.querySelector('button[type="submit"]');
    // Kept as markup, not text: the label shares the button with the loader
    // span, which has to survive the done-label swap to spin again later.
    var idleHtml = button.innerHTML;
    var doneLabel = button.getAttribute('data-done-label') || button.textContent.trim();
    // Matches the broker's cooldown: the button wakes up exactly when a
    // resend would be accepted again.
    var COOLDOWN_MS = 60000;

    form.addEventListener('submit', function (event) {
        if (form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();

        button.disabled = true;
        button.classList.add('is-loading');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                button.classList.remove('is-loading');

                if (!result.ok) {
                    window.armoToast.show(result.data.message || 'Could not send the reset link.', 4000, 'error');
                    button.disabled = false;
                    return;
                }

                window.armoToast.show(result.data.message);
                button.textContent = doneLabel;

                setTimeout(function () {
                    button.innerHTML = idleHtml;
                    button.disabled = false;
                }, COOLDOWN_MS);
            })
            .catch(function () {
                // A response that isn't JSON means something unexpected —
                // fall back to the plain post and its flash message.
                form.setAttribute('data-bypass', '1');
                form.submit();
            });
    });
})();
