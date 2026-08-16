(function () {
    var modal = document.getElementById('add-to-cart-modal');

    if (!modal) {
        return;
    }

    var image = document.getElementById('add-to-cart-modal-image');
    var name = document.getElementById('add-to-cart-modal-name');
    var variant = document.getElementById('add-to-cart-modal-variant');
    var variantValue = document.getElementById('add-to-cart-modal-variant-value');
    var price = document.getElementById('add-to-cart-modal-price');
    var original = document.getElementById('add-to-cart-modal-original');
    var meta = document.getElementById('add-to-cart-modal-meta');
    var cartLink = document.getElementById('add-to-cart-modal-cart-link');
    var limitModal = document.getElementById('stock-limit-modal');

    function bindModal(dialog) {
        if (!dialog) {
            return;
        }

        dialog.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                dialog.close();
            });
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    }

    bindModal(modal);
    bindModal(limitModal);

    function updateCartBadges(count) {
        document.querySelectorAll('.cart-badge').forEach(function (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count <= 0;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.matches('.card-cart, .add-to-cart-form') || form.hasAttribute('data-bypass')) {
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
                var data = result.data || {};

                if (!result.ok) {
                    if (data.code === 'stock_limit' && limitModal) {
                        limitModal.showModal();
                        return;
                    }

                    throw new Error('add-to-cart-failed');
                }

                image.src = data.product.image;
                image.alt = data.product.name;
                name.textContent = data.product.name;
                if (variant && variantValue) {
                    variantValue.textContent = data.product.variant || '';
                    variant.hidden = ! data.product.variant;
                }
                if (price) {
                    price.textContent = data.product.price || '';
                    price.hidden = ! data.product.price;
                }
                if (original) {
                    original.textContent = data.product.original_price || '';
                    original.hidden = ! data.product.original_price;
                }
                var quantityLabel = modal.getAttribute('data-quantity-label') || 'Quantité : :count';
                meta.textContent = quantityLabel.replace(':count', data.quantity);
                cartLink.href = data.cartUrl;
                updateCartBadges(data.cartCount);
                modal.showModal();
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
