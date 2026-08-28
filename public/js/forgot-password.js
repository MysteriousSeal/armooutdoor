(function () {
    var form = document.getElementById('forgot-password-form');

    if (!form) {
        return;
    }

    var submitButton = form.querySelector('button[type="submit"]');
    var emailField = form.querySelector('input[name="email"]');
    var toastTimeout = null;

    function showToast(text, type) {
        var toast = document.getElementById('store-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'store-toast';
            document.body.appendChild(toast);
        }

        toast.className = 'store-toast is-' + (type || 'success');
        toast.setAttribute('role', 'status');
        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 4500);
    }

    function setError(text) {
        var group = emailField.closest('.form-group');
        var error = group.querySelector('.form-error');

        if (!text) {
            emailField.classList.remove('is-invalid');
            if (error) {
                error.remove();
            }
            return;
        }

        emailField.classList.add('is-invalid');

        if (!error) {
            error = document.createElement('p');
            error.className = 'form-error';
            group.appendChild(error);
        }

        error.textContent = text;
    }

    form.addEventListener('submit', function (event) {
        if (form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();
        setError(null);

        submitButton.disabled = true;
        submitButton.classList.add('is-loading');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (response) {
                // A 429 from the route throttle carries an HTML page, not
                // JSON: handled here, before parsing, so it shows inline
                // instead of tripping the fallback into a raw error page.
                if (response.status === 429) {
                    return { status: 429, ok: false, data: {} };
                }

                return response.json().then(function (data) {
                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                submitButton.classList.remove('is-loading');

                if (result.status === 429) {
                    setError(form.getAttribute('data-throttle-message') || 'Trop de tentatives. Patientez une minute.');
                    submitButton.disabled = false;
                    return;
                }

                if (result.status === 422 && result.data.errors) {
                    setError(result.data.errors.email ? result.data.errors.email[0] : null);
                    submitButton.disabled = false;
                    emailField.focus();
                    return;
                }

                if (!result.ok) {
                    throw new Error('forgot-password-failed');
                }

                // Sent: the form locks — asking again inside the cooldown
                // could only produce an error, so the button says done and
                // stays that way.
                showToast(result.data.message, 'success');
                emailField.readOnly = true;
                submitButton.textContent = submitButton.getAttribute('data-done-label') || submitButton.textContent;
            })
            .catch(function () {
                // Anything unexpected falls back to the plain form post, so
                // the page never dead-ends on a JS failure.
                form.setAttribute('data-bypass', '1');
                form.submit();
            });
    });
})();
