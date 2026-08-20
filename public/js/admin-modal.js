(function () {
    // Marks an element as wired so bind() can run again over a region that
    // was swapped in dynamically without doubling up listeners on the rest.
    function once(el, flag) {
        if (el.dataset[flag] === '1') {
            return false;
        }

        el.dataset[flag] = '1';

        return true;
    }

    function bind(root) {
        var scope = root || document;

        scope.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            if (!once(btn, 'modalOpenBound')) {
                return;
            }

            btn.addEventListener('click', function () {
                var modal = document.getElementById(btn.getAttribute('data-modal-open'));
                if (modal) {
                    modal.showModal();
                }
            });
        });

        scope.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            if (!once(btn, 'modalCloseBound')) {
                return;
            }

            btn.addEventListener('click', function () {
                var modal = btn.closest('dialog');
                if (modal) {
                    modal.close();
                }
            });
        });

        scope.querySelectorAll('dialog.modal').forEach(function (modal) {
            if (!once(modal, 'modalBound')) {
                return;
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.close();
                }
            });

            if (modal.hasAttribute('data-autoopen')) {
                modal.showModal();
            }
        });
    }

    // Exposed so a page that replaces part of the DOM can re-wire it.
    window.armoModals = { bind: bind };

    bind();
})();
