(function () {
    var form = document.querySelector('[data-register-form]');

    if (!form) {
        return;
    }

    // The form is novalidate: these are the browser checks, redone in the
    // shop's own words. The server enforces every one of them regardless.
    var terms = form.querySelector('[data-terms]');
    var termsWarning = form.querySelector('[data-terms-warning]');

    function warningFor(input) {
        if (input === terms) {
            return termsWarning;
        }

        // The password fields live inside the toggle's wrapper; the
        // warning goes after whichever box holds the input.
        var anchor = input.closest('.password-field') || input;
        var next = anchor.nextElementSibling;

        if (next && next.hasAttribute('data-client-error')) {
            return next;
        }

        var p = document.createElement('p');
        p.className = 'form-error';
        p.setAttribute('data-client-error', '');
        p.hidden = true;
        anchor.parentNode.insertBefore(p, anchor.nextSibling);

        return p;
    }

    function show(input, message) {
        var warning = warningFor(input);

        if (message) {
            warning.textContent = input === terms ? warning.textContent : message;
            warning.hidden = false;

            return true;
        }

        warning.hidden = true;

        return false;
    }

    function messageFor(input) {
        var value = input.value.trim();

        if (input === terms) {
            return terms.checked ? null : 'shown';
        }

        if (value === '') {
            return {
                first_name: 'Veuillez renseigner votre prénom.',
                last_name: 'Veuillez renseigner votre nom.',
                email: 'Veuillez renseigner votre adresse e-mail.',
                password: 'Veuillez choisir un mot de passe.',
                password_confirmation: 'Veuillez confirmer votre mot de passe.',
            }[input.name] || 'Veuillez renseigner ce champ.';
        }

        if (input.name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'Cette adresse e-mail est invalide.';
        }

        if (input.name === 'password' && value.length < 8) {
            return '8 caractères minimum.';
        }

        if (input.name === 'password_confirmation' && value !== form.querySelector('[name="password"]').value) {
            return 'Les deux mots de passe ne correspondent pas.';
        }

        return null;
    }

    var fields = Array.prototype.slice.call(
        form.querySelectorAll('input[name]')
    ).filter(function (input) { return input.type !== 'hidden'; });

    form.addEventListener('submit', function (event) {
        var firstInvalid = null;

        fields.forEach(function (input) {
            if (show(input, messageFor(input)) && !firstInvalid) {
                firstInvalid = input;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
        }
    });

    // Fixing a field clears its own warning on the spot.
    fields.forEach(function (input) {
        input.addEventListener(input === terms ? 'change' : 'input', function () {
            if (messageFor(input) === null) {
                show(input, null);
            }
        });
    });
})();
