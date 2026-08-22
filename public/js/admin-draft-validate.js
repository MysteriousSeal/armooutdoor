(function () {
    var button = document.querySelector('[data-draft-validate]');

    if (!button) {
        return;
    }

    // Le bouton part caché : sans script la modale ne s'ouvrirait pas et le
    // clic ne ferait rien. Le formulaire d'édition reste la voie de secours.
    button.hidden = false;
})();
