(function () {
    // 182 jours : la CNIL recommande de ne pas redemander avant six mois,
    // refus comme accord.
    var MAX_AGE = 182 * 24 * 60 * 60;

    var banner = document.querySelector('[data-cookie-banner]');

    if (banner) {
        banner.addEventListener('click', function (event) {
            var button = event.target.closest('[data-cookie-choice]');

            if (!button) {
                return;
            }

            document.cookie = 'cookie_consent=' + button.getAttribute('data-cookie-choice')
                + '; max-age=' + MAX_AGE + '; path=/; samesite=lax';
            banner.remove();
        });
    }

    // Retirer son consentement doit être aussi simple que le donner : le
    // lien « Cookies » du pied de page efface le choix et fait revenir le
    // bandeau au rechargement.
    var reopen = document.querySelector('[data-cookie-reopen]');

    if (reopen) {
        reopen.addEventListener('click', function (event) {
            event.preventDefault();
            document.cookie = 'cookie_consent=; max-age=0; path=/; samesite=lax';
            window.location.reload();
        });
    }
})();
