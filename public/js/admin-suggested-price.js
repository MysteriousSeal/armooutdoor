(function () {
    var modal = document.getElementById('suggested-price-modal');

    if (!modal) {
        return;
    }

    var form = modal.querySelector('[data-suggested-price-form]');
    var submit = form.querySelector('button[type="submit"]');
    var error = form.querySelector('[data-suggested-price-error]');
    var trigger = null;

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.admin-suggested-price');

        if (!button || button.disabled) {
            return;
        }

        trigger = button;

        var row = button.closest('tr');
        var current = row ? row.querySelector('.admin-price') : null;

        form.action = button.dataset.priceUrl;
        form.elements.price_cents.value = button.dataset.suggestedPrice;
        modal.querySelector('[data-suggested-price-product]').textContent = button.dataset.productName;
        modal.querySelector('[data-suggested-price-amount]').textContent = button.dataset.suggestedPriceLabel;
        modal.querySelector('[data-suggested-price-current]').textContent = current ? current.textContent.trim() : '';
        modal.querySelector('[data-suggested-price-discount]').hidden = button.dataset.hasDiscount !== '1';
        error.hidden = true;

        modal.showModal();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!trigger || submit.disabled) {
            return;
        }

        submit.disabled = true;
        error.hidden = true;

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
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                var row = trigger.closest('tr');
                var price = row.querySelector('.admin-price');
                var cut = row.querySelector('.admin-price-cut-value');

                if (price) {
                    price.textContent = data.price;
                }

                if (cut) {
                    cut.textContent = data.effective_price;
                }

                // Same verdict as the server's, on the price sold at with any
                // discount: a discount can still leave it below the suggestion.
                var suggested = Number(trigger.dataset.suggestedPrice);

                trigger.classList.remove('is-high', 'is-low', 'is-ok');
                trigger.classList.add(data.effective_price_cents > suggested ? 'is-high' : (data.effective_price_cents < suggested ? 'is-low' : 'is-ok'));
                trigger.disabled = true;

                modal.close();

                if (window.armoToast) {
                    window.armoToast.show('Price set to ' + data.price + '.');
                }
            })
            .catch(function () {
                error.hidden = false;
            })
            .finally(function () {
                submit.disabled = false;
            });
    });
})();
