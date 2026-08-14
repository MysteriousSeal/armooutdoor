(function () {
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-modal-open'));
            if (modal) {
                modal.showModal();
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('dialog');
            if (modal) {
                modal.close();
            }
        });
    });

    document.querySelectorAll('dialog.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.close();
            }
        });

        if (modal.hasAttribute('data-autoopen')) {
            modal.showModal();
        }
    });
})();
