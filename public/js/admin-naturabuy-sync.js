(function () {
    var form = document.querySelector('.nb-sync-form');

    if (!form) {
        return;
    }

    // La synchronisation prend quelques secondes : sans retour visuel, on
    // clique deux fois et on lance deux appels à leur API.
    form.addEventListener('submit', function () {
        var button = form.querySelector('.nb-sync-btn');

        if (!button) {
            return;
        }

        var label = button.querySelector('.nb-sync-text');

        button.classList.add('is-syncing');
        button.disabled = true;

        if (label && button.dataset.syncingLabel) {
            label.textContent = button.dataset.syncingLabel;
        }
    });
})();
