(function () {
    // The regions a status change rewrites. Everything else on the page is
    // left alone, so open form state elsewhere survives.
    var REGIONS = ['order-heading', 'order-actions', 'order-downloads', 'order-timeline', 'order-modals'];

    var page = document.querySelector('.admin-list-page');

    if (!page) {
        return;
    }

    function isStatusForm(form) {
        var action = form.getAttribute('action') || '';

        return /\/(prepare|ship|refund)$/.test(action);
    }

    function swapRegions(html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');

        REGIONS.forEach(function (id) {
            var fresh = parsed.getElementById(id);
            var current = document.getElementById(id);

            if (fresh && current) {
                current.replaceWith(fresh);
            }
        });

        // The swapped buttons and dialogs carry no listeners yet.
        if (window.armoModals) {
            window.armoModals.bind(document);
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !isStatusForm(form) || form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('status-change-failed');
                }

                return response.json();
            })
            .then(function (data) {
                var dialog = form.closest('dialog');

                if (dialog && dialog.open) {
                    dialog.close();
                }

                swapRegions(data.html);
                window.armoToast.show(data.message);
            })
            .catch(function () {
                // Anything unexpected — a 403 on a refund, a dropped
                // connection — falls back to the ordinary page submit so the
                // admin still gets the real answer.
                form.setAttribute('data-bypass', '1');
                form.submit();
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                }
            });
    });
})();
