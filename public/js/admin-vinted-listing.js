(function () {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.vinted-copy'));

    // Le compte de caractères et le nom des fichiers choisis vivent ici
    // aussi : ils n'ont rien à voir avec le presse-papiers, et doivent
    // marcher même là où il manque.
    countCharacters();
    reportPickedFiles();

    if (buttons.length === 0) {
        return;
    }

    // Sans presse-papiers — un vieux navigateur, une page servie sans HTTPS —
    // les boutons ne feraient rien en silence. Mieux vaut qu'ils ne soient
    // pas là : le champ est juste à côté, il se sélectionne à la main.
    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
        buttons.forEach(function (button) {
            button.hidden = true;
        });

        return;
    }

    function label(button, text) {
        button.textContent = text;
    }

    buttons.forEach(function (button) {
        var idle = button.textContent;
        var timer = null;

        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-copy-from'));

            if (!field) {
                return;
            }

            var value = field.value;

            // Le champ prix de Vinted attend une virgule et refuse le point.
            if (button.hasAttribute('data-copy-decimal-comma')) {
                value = value.replace('.', ',');
            }

            navigator.clipboard.writeText(value).then(function () {
                clearTimeout(timer);
                button.classList.add('is-copied');
                label(button, 'Copied');

                timer = setTimeout(function () {
                    button.classList.remove('is-copied');
                    label(button, idle);
                }, 1600);
            }, function () {
                // Le refus du navigateur se dit : un bouton qui ne réagit pas
                // laisse croire que la valeur est partie.
                clearTimeout(timer);
                label(button, 'Press ⌘C');

                timer = setTimeout(function () {
                    label(button, idle);
                }, 2400);

                field.focus();
                field.select();
            });
        });
    });

    /**
     * Combien de caractères sont écrits, et — pour le titre — à partir de
     * quand Vinted coupera sur un téléphone. Une information, jamais un
     * blocage : un titre plus long reste un titre valide.
     */
    function countCharacters() {
        var fields = Array.prototype.slice.call(document.querySelectorAll('[data-counter-for]'));

        fields.forEach(function (field) {
            var target = document.getElementById(field.getAttribute('data-counter-for'));

            if (!target) {
                return;
            }

            var ideal = parseInt(field.getAttribute('data-counter-ideal'), 10);

            function render() {
                var length = field.value.length;

                if (isNaN(ideal)) {
                    target.textContent = length + (length === 1 ? ' character' : ' characters');
                    target.className = '';

                    return;
                }

                target.textContent = length + ' / ' + ideal + ' before Vinted trims it';
                target.className = length > ideal ? 'is-over' : '';
            }

            field.addEventListener('input', render);
            render();
        });
    }

    /** Ce que le sélecteur de fichiers a retenu, dit en toutes lettres. */
    function reportPickedFiles() {
        var notes = Array.prototype.slice.call(document.querySelectorAll('[data-picked-for]'));

        notes.forEach(function (note) {
            var input = document.getElementById(note.getAttribute('data-picked-for'));

            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                var count = input.files ? input.files.length : 0;

                note.hidden = count === 0;
                note.textContent = count === 1
                    ? '1 photo ready to upload — save to keep it'
                    : count + ' photos ready to upload — save to keep them';
            });
        });
    }
})();