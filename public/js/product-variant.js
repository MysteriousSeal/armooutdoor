(function () {
    var form = document.querySelector('.add-to-cart-form');

    if (!form) {
        return;
    }

    var radios = form.querySelectorAll('input[name="variant_id"]');

    if (!radios.length) {
        return;
    }

    var priceEl = document.getElementById('product-detail-price');
    var badgeEl = document.getElementById('product-stock-badge');
    var qtyInput = form.querySelector('.qty-stepper-input');

    function applyVariant(radio) {
        if (priceEl && radio.hasAttribute('data-variant-price')) {
            priceEl.textContent = radio.getAttribute('data-variant-price');
        }

        if (badgeEl) {
            badgeEl.className = 'stock-badge ' + (radio.getAttribute('data-variant-stock-class') || '');
            badgeEl.textContent = radio.getAttribute('data-variant-stock-label') || '';
        }

        if (qtyInput) {
            var max = parseInt(radio.getAttribute('data-variant-max'), 10) || 0;
            qtyInput.max = max;

            if ((parseInt(qtyInput.value, 10) || 0) > max) {
                qtyInput.value = Math.max(1, max);
            }
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyVariant(radio);
        });
    });
})();
