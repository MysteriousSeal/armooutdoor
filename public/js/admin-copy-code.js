(function () {
    function showToast(text) {
        window.armoToast.show(text, 2000);
    }

    function copyWithExecCommand(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();

        var succeeded = false;
        try {
            succeeded = document.execCommand('copy');
        } catch (e) {
            succeeded = false;
        }

        document.body.removeChild(input);

        return succeeded ? Promise.resolve() : Promise.reject(new Error('execCommand copy failed'));
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () {
                return copyWithExecCommand(text);
            });
        }

        return copyWithExecCommand(text);
    }

    function handleCopy(target) {
        var code = target.getAttribute('data-copy-code');

        copyText(code).then(function () {
            showToast('Copied "' + code + '" to clipboard.');
        }).catch(function () {
            showToast('Could not copy "' + code + '".');
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-copy-code]');

        if (!target) {
            return;
        }

        handleCopy(target);
    });

    // Pas de gestionnaire clavier ici : tout ce qui porte `data-copy-code` est
    // un vrai <button>, qui déclenche déjà un clic sur Entrée et Espace. Le
    // raccourci n'existait que pour un <h2 role="button">, qui n'existe plus.
})();
