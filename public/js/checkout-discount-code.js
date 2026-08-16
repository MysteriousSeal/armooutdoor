(function () {
    var sectionBody = document.getElementById('discount-code-section-body');

    if (!sectionBody) {
        return;
    }

    var toastTimeout = null;

    function showToast(text, type) {
        var toast = document.getElementById('store-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'store-toast';
            document.body.appendChild(toast);
        }

        toast.className = 'store-toast is-' + (type || 'success');
        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2500);
    }

    function applyResult(data, ok) {
        sectionBody.innerHTML = data.sectionHtml;

        var row = document.getElementById('checkout-discount-row');
        if (row) {
            row.hidden = !data.applied;

            var label = document.getElementById('checkout-discount-label');
            if (label) {
                label.textContent = data.discountLabel || '';
            }

            var value = document.getElementById('checkout-discount-value');
            if (value) {
                value.textContent = data.discountValueText || '';
            }
        }

        if (window.armoCheckout) {
            window.armoCheckout.discountCents = data.discountCents || 0;
            if (typeof window.armoCheckout.syncTotals === 'function') {
                window.armoCheckout.syncTotals();
            }
        }

        showToast(data.message, ok ? 'success' : 'error');
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.matches('.discount-code-form, .discount-code-remove-form') || form.hasAttribute('data-bypass')) {
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
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.data || !result.data.sectionHtml) {
                    throw new Error('discount-code-failed');
                }
                applyResult(result.data, result.ok);
            })
            .catch(function () {
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
