(function () {
    // A show/hide eye on every password field of an auth form. Built at
    // runtime: without JavaScript no dead button ships, the field is a
    // plain password input.
    var eye = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.75"/></svg>';
    var eyeOff = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M4 20 20 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>';

    document.querySelectorAll('.auth-form input[type="password"]').forEach(function (input) {
        var wrap = document.createElement('span');
        wrap.className = 'password-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-label', 'Afficher le mot de passe');
        button.innerHTML = eye;
        wrap.appendChild(button);

        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = show ? eyeOff : eye;
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            input.focus();
        });
    });
})();
