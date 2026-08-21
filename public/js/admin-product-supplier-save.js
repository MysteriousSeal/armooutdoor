(function () {
    var row = document.getElementById('supplier-save-row');
    var confirmBtn = document.getElementById('supplier-save-confirm');
    var modal = document.getElementById('supplier-save-modal');

    if (!row || !confirmBtn || !modal) {
        return;
    }

    var endpoint = row.getAttribute('data-endpoint');
    var token = document.querySelector('meta[name="csrf-token"]');

    if (!endpoint || !token) {
        return;
    }

    // Le bouton n'apparaît que si le script tourne : sans lui, le formulaire
    // complet en bas de page reste le seul chemin, et il fonctionne.
    row.hidden = false;

    var FIELDS = [
        'supplier_id',
        'supplier_reference',
        'supplier_product_url',
        'supplier_price',
        'markup_percent',
    ];

    function payload() {
        var data = {};

        FIELDS.forEach(function (name) {
            var el = document.getElementById(name);

            if (el) {
                data[name] = el.value;
            }
        });

        var available = document.getElementById('available_at_supplier');
        data.available_at_supplier = available && available.checked ? 1 : 0;

        return data;
    }

    function clearErrors() {
        document.querySelectorAll('[data-supplier-error]').forEach(function (el) {
            el.remove();
        });
    }

    function showError(field, message) {
        var input = document.getElementById(field);

        if (!input) {
            return;
        }

        var p = document.createElement('p');
        p.className = 'form-error';
        p.setAttribute('data-supplier-error', '');
        p.textContent = message;
        input.insertAdjacentElement('afterend', p);
    }

    function toast(text) {
        if (window.armoToast) {
            window.armoToast.show(text);
        }
    }

    confirmBtn.addEventListener('click', function () {
        confirmBtn.disabled = true;
        clearErrors();

        fetch(endpoint, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token.getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload()),
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
            })
            .then(function (result) {
                modal.close();

                if (result.ok) {
                    toast(result.body.message || 'Supplier details saved.');

                    return;
                }

                // 422 : on remet les messages sous les champs concernés,
                // comme le ferait un aller-retour serveur.
                if (result.status === 422 && result.body.errors) {
                    Object.keys(result.body.errors).forEach(function (field) {
                        showError(field, result.body.errors[field][0]);
                    });
                    toast('Supplier details not saved — check the fields.');

                    return;
                }

                toast(result.body.message || 'Supplier details could not be saved.');
            })
            .catch(function () {
                modal.close();
                toast('Supplier details could not be saved.');
            })
            .then(function () {
                confirmBtn.disabled = false;
            });
    });
})();
