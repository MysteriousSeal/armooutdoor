// The accounting entry form, for adding as well as correcting.
//
// One dialog serves both: two identical forms would have drifted apart at the
// first field added. Opening it is the modal script's job; this one only
// points the form at the right URL and fills it in beforehand.
//
// The buttons carry what is needed: `data-entry-action` the URL,
// `data-entry` the values as JSON, `data-entry-label` the name to confirm
// a deletion against.
(function () {
    'use strict';

    var form = document.getElementById('entry-form');

    // Purchases months render no form: nothing to wire.
    if (!form) {
        return;
    }

    var method = document.getElementById('entry-method');
    var submit = document.getElementById('entry-submit');
    var title = document.getElementById('entry-modal-title');
    // Kept aside because editing overwrites the action, and adding has to be
    // able to put the original one back.
    var addAction = form.getAttribute('action');

    /** Writes the values of an entry into the fields of the same name. */
    function fill(values) {
        Object.keys(values).forEach(function (name) {
            var field = form.elements[name];

            if (field) {
                field.value = values[name] === null ? '' : values[name];
            }
        });
    }

    // "Add entry": an empty form pointed at the create URL.
    document.querySelectorAll('[data-entry-new]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            form.setAttribute('action', addAction);
            method.value = 'POST';
            title.textContent = 'Add an entry';
            submit.textContent = 'Add entry';
            form.reset();
        });
    });

    // The pencil on a line: the same form, filled in and pointed at that entry.
    document.querySelectorAll('[data-entry-edit]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            form.setAttribute('action', trigger.getAttribute('data-entry-action'));
            // The method goes through the hidden field: an HTML form cannot
            // send a PUT.
            method.value = 'PUT';
            title.textContent = 'Edit this entry';
            submit.textContent = 'Save changes';

            try {
                fill(JSON.parse(trigger.getAttribute('data-entry')));
            } catch (error) {
                // An unreadable attribute must not block the dialog: the form
                // then opens on whatever values it already held.
            }
        });
    });

    var deleteForm = document.getElementById('entry-delete-form');
    var deleteLabel = document.getElementById('entry-delete-label');

    // The bin on a line: aims the confirmation form and names what will go.
    document.querySelectorAll('[data-entry-delete]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            deleteForm.setAttribute('action', trigger.getAttribute('data-entry-action'));
            deleteLabel.textContent = trigger.getAttribute('data-entry-label') || 'this entry';
        });
    });
})();

// Attaching an invoice: the file picker is the whole control, so the form
// sends as soon as a file is chosen rather than asking for a second click.
(function () {
    'use strict';

    document.querySelectorAll('[data-invoice-file]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.files.length > 0) {
                input.form.submit();
            }
        });
    });
})();
