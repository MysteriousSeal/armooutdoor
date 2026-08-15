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
    var currentEl = document.getElementById('product-variant-current');
    var mainImage = document.getElementById('product-detail-main-image');
    var qtyInput = form.querySelector('.qty-stepper-input');
    var submitBtn = form.querySelector('button[type="submit"]');
    var stepperButtons = form.querySelectorAll('.qty-stepper-btn');

    function applyVariant(radio) {
        if (currentEl && radio.hasAttribute('data-variant-label')) {
            currentEl.textContent = radio.getAttribute('data-variant-label') || '';
        }

        if (mainImage && radio.getAttribute('data-variant-image')) {
            mainImage.src = radio.getAttribute('data-variant-image');
        }

        if (priceEl && radio.hasAttribute('data-variant-price')) {
            priceEl.textContent = radio.getAttribute('data-variant-price');
        }

        var max = parseInt(radio.getAttribute('data-variant-max'), 10) || 0;
        var outOfStock = max < 1;

        if (qtyInput) {
            qtyInput.max = max;
            qtyInput.disabled = outOfStock;

            if (outOfStock) {
                qtyInput.value = 1;
            } else if ((parseInt(qtyInput.value, 10) || 0) > max) {
                qtyInput.value = max;
            } else if ((parseInt(qtyInput.value, 10) || 0) < 1) {
                qtyInput.value = 1;
            }
        }

        stepperButtons.forEach(function (button) {
            button.disabled = outOfStock;
        });

        if (submitBtn) {
            submitBtn.disabled = outOfStock;
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyVariant(radio);
        });
    });
})();
