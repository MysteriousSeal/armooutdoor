// Le formulaire d'écriture comptable, en ajout comme en correction.
//
// Une seule boîte de dialogue sert aux deux : deux formulaires identiques se
// seraient désaccordés au premier champ ajouté.
(function () {
    'use strict';

    var form = document.getElementById('entry-form');

    if (!form) {
        return;
    }

    var method = document.getElementById('entry-method');
    var submit = document.getElementById('entry-submit');
    var title = document.getElementById('entry-modal-title');
    var addAction = form.getAttribute('action');

    function fill(values) {
        Object.keys(values).forEach(function (name) {
            var field = form.elements[name];

            if (field) {
                field.value = values[name] === null ? '' : values[name];
            }
        });
    }

    document.querySelectorAll('[data-entry-new]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            form.setAttribute('action', addAction);
            method.value = 'POST';
            title.textContent = 'Add an entry';
            submit.textContent = 'Add entry';
            form.reset();
        });
    });

    document.querySelectorAll('[data-entry-edit]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            form.setAttribute('action', trigger.getAttribute('data-entry-action'));
            // La méthode passe par le champ caché : un formulaire HTML ne sait
            // pas envoyer un PUT.
            method.value = 'PUT';
            title.textContent = 'Edit this entry';
            submit.textContent = 'Save changes';

            try {
                fill(JSON.parse(trigger.getAttribute('data-entry')));
            } catch (error) {
                // Un attribut illisible ne doit pas bloquer l'ouverture : le
                // formulaire s'ouvre alors sur les valeurs précédentes.
            }
        });
    });

    var deleteForm = document.getElementById('entry-delete-form');
    var deleteLabel = document.getElementById('entry-delete-label');

    document.querySelectorAll('[data-entry-delete]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            deleteForm.setAttribute('action', trigger.getAttribute('data-entry-action'));
            deleteLabel.textContent = trigger.getAttribute('data-entry-label') || 'this entry';
        });
    });
})();
