(function () {
    var toastTimeout = null;

    function showToast(text, type) {
        var toast = document.getElementById('store-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'store-toast';
            document.body.appendChild(toast);
        }

        toast.className = 'store-toast is-' + (type || 'success');
        toast.setAttribute('role', 'status');
        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 4500);
    }

    function updateCartBadges(count) {
        document.querySelectorAll('.cart-badge').forEach(function (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count <= 0;
        });
    }

    function applyUpdate(form, input, data) {
        var line = form.closest('.cart-line');

        if (data.removed) {
            if (line) {
                line.remove();
            }
        } else if (line) {
            input.value = data.quantity;

            var unitPriceEl = line.querySelector('.cart-line-unit-price');
            var unitPriceValue = line.querySelector('.cart-line-unit-price-value');
            var unitPriceQty = line.querySelector('.cart-line-unit-price-qty');
            var totalEl = line.querySelector('.cart-line-total');
            var totalValue = line.querySelector('.cart-line-total-value');

            if (unitPriceEl) {
                unitPriceEl.hidden = data.quantity <= 1;
            }
            if (unitPriceValue) {
                unitPriceValue.textContent = data.unitPrice;
            }
            if (unitPriceQty) {
                unitPriceQty.textContent = data.quantity;
            }
            if (totalValue) {
                totalValue.textContent = data.lineTotal;
            }
            if (totalEl) {
                var totalOriginal = totalEl.querySelector('.card-price-original');
                if (totalOriginal) {
                    totalOriginal.hidden = !(totalEl.getAttribute('data-has-discount') === '1' && data.quantity <= 1);
                }
            }
        }

        if (data.isEmpty) {
            window.location.reload();
            return;
        }

        document.querySelectorAll('.cart-summary-total').forEach(function (el) {
            el.textContent = data.subtotal;
        });

        if (data.itemCountLabel) {
            var header = document.getElementById('cart-item-count-header');
            if (header) {
                header.textContent = data.itemCountLabel;
            }
            document.querySelectorAll('.cart-summary-count').forEach(function (el) {
                el.textContent = data.itemCountLabel;
            });
        }

        var shippingEstimateEl = document.querySelector('.cart-shipping-estimate');
        if (shippingEstimateEl) {
            if (data.estimatedShippingDateText) {
                shippingEstimateEl.hidden = false;
                var timeEl = shippingEstimateEl.querySelector('time');
                if (timeEl) {
                    timeEl.textContent = data.estimatedShippingDateText;
                    if (data.estimatedShippingDate) {
                        timeEl.setAttribute('datetime', data.estimatedShippingDate);
                    }
                }
            } else {
                shippingEstimateEl.hidden = true;
            }
        }

        var shippingWrap = document.querySelector('.cart-summary-shipping');
        if (shippingWrap) {
            shippingWrap.hidden = !data.shippingVisible;
            shippingWrap.classList.toggle('is-free', !!data.shippingIsFree);
            var shippingValue = shippingWrap.querySelector('.cart-summary-shipping-value');
            if (shippingValue && data.shippingValueText) {
                shippingValue.textContent = data.shippingValueText;
            }
        }

        updateCartBadges(data.itemCount);
        showToast(data.message);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.matches('.cart-qty-form, .cart-line-remove') || form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();

        var input = form.querySelector('input[name="quantity"]');
        var max = input ? parseInt(input.max, 10) : NaN;
        var value = input ? parseInt(input.value, 10) : NaN;

        if (input && !isNaN(max) && !isNaN(value) && value > max) {
            var label = form.getAttribute('data-stock-limit-label') || 'Plus que :count disponible(s) pour cet article.';
            showToast(label.replace(':count', max), 'error');
            return;
        }

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
                    throw new Error('cart-update-failed');
                }
                return response.json();
            })
            .then(function (data) {
                applyUpdate(form, input, data);
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
