// Les groupes de la navigation de l'admin.
//
// Ils s'ouvrent au clic, comme le menu « Actions » des listes : au survol, un
// menu s'ouvre par accident en traversant la barre, et ne s'ouvre jamais sur
// un écran tactile.
(function () {
    'use strict';

    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-nav-toggle]'));

    if (groups.length === 0) {
        return;
    }

    function closeAll(except) {
        groups.forEach(function (trigger) {
            if (trigger === except) {
                return;
            }

            trigger.setAttribute('aria-expanded', 'false');
            trigger.nextElementSibling.hidden = true;
        });
    }

    groups.forEach(function (trigger) {
        var menu = trigger.nextElementSibling;

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();

            var willOpen = menu.hidden;

            closeAll(trigger);
            menu.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function () {
        closeAll(null);
    });

    // Échap referme et rend le clavier au déclencheur : sans quoi le focus
    // reste sur un lien caché.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        groups.forEach(function (trigger) {
            if (trigger.getAttribute('aria-expanded') === 'true') {
                trigger.focus();
            }
        });

        closeAll(null);
    });

    // Quitter le groupe au clavier le referme, comme un clic à côté.
    document.addEventListener('focusin', function (event) {
        groups.forEach(function (trigger) {
            var group = trigger.parentElement;

            if (!group.contains(event.target)) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.nextElementSibling.hidden = true;
            }
        });
    });
})();
