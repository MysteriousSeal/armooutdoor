// The backups page.
//
// Writing an archive runs in the request and takes a while, so the button
// says what is happening and refuses a second press: two archives written at
// once would fight over the same directory for no gain.
(function () {
    'use strict';

    var form = document.querySelector('[data-backup-form]');

    if (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('[data-backup-submit]');

            if (button) {
                button.disabled = true;
                button.textContent = 'Backing up…';
            }
        });
    }

    // One dialog for every row: the button carries the URL and the name.
    var deleteForm = document.getElementById('backup-delete-form');
    var deleteLabel = document.getElementById('backup-delete-label');

    if (!deleteForm) {
        return;
    }

    document.querySelectorAll('[data-backup-delete]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            deleteForm.setAttribute('action', trigger.getAttribute('data-backup-action'));
            deleteLabel.textContent = trigger.getAttribute('data-backup-label') || 'This backup';
        });
    });
})();
