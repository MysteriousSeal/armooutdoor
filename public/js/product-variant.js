(function () {
    var form = document.querySelector('.add-to-cart-form');

    if (!form) {
        return;
    }

    var radios = form.querySelectorAll('input[name="variant_id"]');

    if (!radios.length) {
        return;
    }

    var priceEl = document.getElementById('product-detail-price-current');
    var priceOriginalEl = document.getElementById('product-detail-price-original');
    var discountBadgeEl = document.getElementById('product-detail-discount-badge');
    var countdownEl = document.getElementById('discount-countdown');
    var currentEl = document.getElementById('product-variant-current');
    var mainImage = document.getElementById('product-detail-main-image');
    var qtyInput = form.querySelector('.qty-stepper-input');
    var buyRow = form.querySelector('.product-buy-row');
    var leadTimeEl = document.getElementById('product-lead-time');
    var leadTimeValueEl = document.getElementById('product-lead-time-value');
    var leadTimeTemplate = document.getElementById('variant-lead-times');
    var leadTimesByVariant = {};

    if (leadTimeTemplate) {
        leadTimeTemplate.content.querySelectorAll('[data-variant-id]').forEach(function (node) {
            leadTimesByVariant[node.getAttribute('data-variant-id')] = {
                visible: node.getAttribute('data-lead-time-visible') === '1',
                text: node.getAttribute('data-lead-time-text') || '',
            };
        });
    }

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

        if (priceOriginalEl) {
            var originalPrice = radio.getAttribute('data-variant-original-price');

            if (originalPrice) {
                priceOriginalEl.textContent = originalPrice;
                priceOriginalEl.hidden = false;
            } else {
                priceOriginalEl.hidden = true;
            }
        }

        if (discountBadgeEl) {
            var discountLabel = radio.getAttribute('data-variant-discount-label');

            if (discountLabel) {
                discountBadgeEl.textContent = discountLabel;
                discountBadgeEl.hidden = false;
            } else {
                discountBadgeEl.hidden = true;
            }
        }

        if (countdownEl) {
            var endsAt = radio.getAttribute('data-variant-discount-ends-at');

            countdownEl.dataset.endsAt = endsAt || '';
            countdownEl.hidden = !endsAt;

            if (window.ProductDiscountCountdown) {
                window.ProductDiscountCountdown.refresh();
            }
        }

        if (leadTimeEl && leadTimeValueEl) {
            var leadTime = leadTimesByVariant[radio.value];

            if (leadTime && leadTime.visible) {
                leadTimeValueEl.textContent = leadTime.text;
                leadTimeEl.hidden = false;
            } else {
                leadTimeEl.hidden = true;
            }
        }

        var max = parseInt(radio.getAttribute('data-variant-max'), 10) || 0;
        var outOfStock = max < 1;

        if (buyRow) {
            buyRow.hidden = outOfStock;
        }

        if (qtyInput) {
            qtyInput.max = max;

            if (outOfStock) {
                qtyInput.value = 1;
            } else if ((parseInt(qtyInput.value, 10) || 0) > max) {
                qtyInput.value = max;
            } else if ((parseInt(qtyInput.value, 10) || 0) < 1) {
                qtyInput.value = 1;
            }
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyVariant(radio);
        });
    });

    var checkedRadio = form.querySelector('input[name="variant_id"]:checked');

    if (checkedRadio) {
        applyVariant(checkedRadio);
    }
})();
