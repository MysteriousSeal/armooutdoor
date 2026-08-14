(function () {
    var config = window.armoCheckout;
    var picker = document.getElementById('relay-picker');
    var payment = document.getElementById('payment-section');
    var search = document.getElementById('relay-search');
    var shippingPrice = document.getElementById('checkout-shipping-price');
    var grandTotal = document.getElementById('checkout-grand-total');
    var sameBilling = document.getElementById('same-billing-address');
    var billingPicker = document.getElementById('billing-address-picker');

    if (!config) {
        return;
    }

    function formatEuros(cents) {
        var amount = (cents / 100).toFixed(2);

        if (config.locale === 'fr') {
            return amount.replace('.', ',') + '\u00a0€';
        }

        return '€' + amount;
    }

    function selectedCarrier() {
        return document.querySelector('input[name="carrier_id"]:checked');
    }

    function syncRelayPicker() {
        var carrier = selectedCarrier();
        var isRelay = carrier && carrier.getAttribute('data-method') === 'relay';
        var inputs = document.querySelectorAll('input[name="relay_point_id"]');

        if (payment) {
            payment.hidden = !carrier;
        }

        if (picker) {
            picker.hidden = !isRelay;
        }

        inputs.forEach(function (input) {
            input.disabled = !isRelay;
            if (!isRelay) {
                input.checked = false;
            }
        });
    }

    function syncTotals() {
        var carrier = selectedCarrier();
        var shippingCents = carrier ? (config.carriers[carrier.value] || 0) : 0;

        if (shippingPrice) {
            shippingPrice.textContent = carrier
                ? (shippingCents === 0 ? 'Gratuite' : formatEuros(shippingCents))
                : '—';
        }

        if (grandTotal) {
            grandTotal.textContent = formatEuros(config.subtotalCents + shippingCents);
        }
    }

    document.querySelectorAll('input[name="carrier_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            syncRelayPicker();
            syncTotals();
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            var query = search.value.trim().toLowerCase();

            document.querySelectorAll('.relay-option').forEach(function (option) {
                var haystack = option.getAttribute('data-search') || '';
                option.hidden = query !== '' && haystack.indexOf(query) === -1;
            });
        });
    }

    function syncBillingPicker() {
        if (!sameBilling || !billingPicker) {
            return;
        }

        var isSame = sameBilling.checked;
        var inputs = billingPicker.querySelectorAll('input[name="billing_address_id"]');

        billingPicker.hidden = isSame;

        inputs.forEach(function (input) {
            input.disabled = isSame;
        });
    }

    if (sameBilling) {
        sameBilling.addEventListener('change', syncBillingPicker);
    }

    syncRelayPicker();
    syncTotals();
    syncBillingPicker();
})();
