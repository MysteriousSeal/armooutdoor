(function () {
    var form = document.getElementById('contact-form');

    if (!form) {
        return;
    }

    var submitButton = form.querySelector('button[type="submit"]');
    var fields = Array.prototype.slice.call(form.querySelectorAll('[data-validate]'));
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

    function errorElementFor(field) {
        return field.closest('.form-group').querySelector('.form-error');
    }

    function setError(field, text) {
        var error = errorElementFor(field);

        if (!text) {
            field.classList.remove('is-invalid');
            if (error) {
                error.remove();
            }
            return;
        }

        field.classList.add('is-invalid');

        if (!error) {
            error = document.createElement('p');
            error.className = 'form-error';
            field.closest('.form-group').appendChild(error);
        }

        error.textContent = text;
    }

    function validateField(field) {
        var value = field.value.trim();
        var label = field.closest('.form-group').querySelector('label').textContent.trim();

        if (field.hasAttribute('required') && value === '') {
            setError(field, 'Le champ « ' + label + ' » est obligatoire.');
            return false;
        }

        if (field.type === 'email' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            setError(field, 'Adresse e-mail invalide.');
            return false;
        }

        setError(field, null);
        return true;
    }

    fields.forEach(function (field) {
        field.addEventListener('blur', function () {
            validateField(field);
        });
    });

    form.addEventListener('submit', function (event) {
        if (form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();

        var isValid = fields.reduce(function (valid, field) {
            return validateField(field) && valid;
        }, true);

        if (!isValid) {
            var firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
        }

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
                return response.json().then(function (data) {
                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.status === 422 && result.data.errors) {
                    fields.forEach(function (field) {
                        var messages = result.data.errors[field.name];
                        setError(field, messages ? messages[0] : null);
                    });
                    return;
                }

                if (!result.ok) {
                    throw new Error('contact-form-failed');
                }

                form.reset();
                fields.forEach(function (field) {
                    setError(field, null);
                });
                showToast(result.data.message, 'success');
            })
            .catch(function () {
                form.setAttribute('data-bypass', '1');
                form.submit();
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    });
})();
