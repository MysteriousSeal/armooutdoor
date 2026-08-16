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

    function selectedAddress() {
        return document.querySelector('input[name="address_id"]:checked');
    }

    var MONDIAL_RELAY_SLUG = 'mondial-relay';
    var relayPointsLoadedFor = null;

    function isMondialRelay(carrier) {
        return !!carrier && carrier.getAttribute('data-carrier-slug') === MONDIAL_RELAY_SLUG;
    }

    function syncRelayPicker() {
        var carrier = selectedCarrier();
        var isRelay = carrier && carrier.getAttribute('data-method') === 'relay';
        var isMondial = isMondialRelay(carrier);
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

        if (!isMondial) {
            relayPointsLoadedFor = null;
            if (relayGrid) {
                relayGrid.innerHTML = '';
            }
            if (relayEmpty) {
                relayEmpty.hidden = !isRelay;
            }
            return;
        }

        var address = selectedAddress();
        var postalCode = address ? address.getAttribute('data-postal-code') : null;

        if (postalCode && postalCode !== relayPointsLoadedFor) {
            loadRelayPoints(postalCode, address.getAttribute('data-country'));
        }
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
            grandTotal.textContent = formatEuros(config.subtotalCents - (config.discountCents || 0) + shippingCents);
        }
    }

    document.querySelectorAll('input[name="carrier_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            syncRelayPicker();
            syncTotals();
        });
    });

    var searchResults = document.getElementById('relay-search-results');
    var postalCodeSearchTimeout = null;
    var postalCodeSearchToken = 0;

    function searchPostalCodes(query) {
        if (!config.postalCodesSearchUrl) {
            return Promise.resolve([]);
        }

        return fetch(config.postalCodesSearchUrl + '?q=' + encodeURIComponent(query), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (data) { return data.results || []; })
            .catch(function () { return []; });
    }

    function renderSearchResults(matches) {
        if (!searchResults) {
            return;
        }

        searchResults.innerHTML = '';

        if (!matches.length) {
            searchResults.hidden = true;
            return;
        }

        matches.forEach(function (pair) {
            var li = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'relay-search-option';
            button.textContent = pair[0] + ' ' + pair[1];
            button.addEventListener('click', function () {
                search.value = pair[0] + ' ' + pair[1];
                searchResults.hidden = true;

                if (isMondialRelay(selectedCarrier())) {
                    loadRelayPoints(pair[0], 'FR');
                }
            });
            li.appendChild(button);
            searchResults.appendChild(li);
        });

        searchResults.hidden = false;
    }

    if (search && searchResults) {
        search.addEventListener('input', function () {
            var query = search.value.trim();

            clearTimeout(postalCodeSearchTimeout);

            if (query === '') {
                searchResults.hidden = true;
                return;
            }

            postalCodeSearchTimeout = setTimeout(function () {
                var token = ++postalCodeSearchToken;

                searchPostalCodes(query).then(function (matches) {
                    if (token !== postalCodeSearchToken) {
                        return;
                    }

                    renderSearchResults(matches);
                });
            }, 200);
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.relay-search-wrap')) {
                searchResults.hidden = true;
            }
        });
    }

    var relayGrid = document.getElementById('relay-points-grid');
    var relayEmpty = document.getElementById('relay-points-empty');
    var relayRequestToken = 0;

    function renderRelayPoint(point) {
        var label = document.createElement('label');
        label.className = 'choice-card relay-option';
        label.setAttribute('data-search', (point.search || '').toLowerCase());

        var input = document.createElement('input');
        input.type = 'radio';
        input.name = 'relay_point_id';
        input.value = point.id;
        input.setAttribute('form', 'checkout-form');
        input.disabled = !(selectedCarrier() && selectedCarrier().getAttribute('data-method') === 'relay');
        input.checked = config.selectedRelayPointId !== null && String(point.id) === String(config.selectedRelayPointId);

        var body = document.createElement('span');
        body.className = 'choice-card-body';

        var title = document.createElement('span');
        title.className = 'choice-card-title relay-title';
        title.textContent = point.name;
        body.appendChild(title);

        var line1 = document.createElement('span');
        line1.className = 'choice-card-meta';
        line1.textContent = point.line1;
        body.appendChild(line1);

        var cityLine = document.createElement('span');
        cityLine.className = 'choice-card-meta';
        cityLine.textContent = point.postal_code + ' ' + point.city;
        body.appendChild(cityLine);

        if (point.hours) {
            var hoursLabel = document.createElement('span');
            hoursLabel.className = 'choice-card-meta relay-hours-label';
            hoursLabel.textContent = config.relayHoursLabel || 'Horaires';
            body.appendChild(hoursLabel);

            var hoursList = document.createElement('ul');
            hoursList.className = 'relay-hours';

            point.hours.split(' · ').forEach(function (line) {
                var spaceIndex = line.indexOf(' ');
                var day = spaceIndex === -1 ? line : line.slice(0, spaceIndex);
                var range = spaceIndex === -1 ? '' : line.slice(spaceIndex + 1);

                var li = document.createElement('li');

                var dayEl = document.createElement('span');
                dayEl.className = 'relay-hours-day';
                dayEl.textContent = day;
                li.appendChild(dayEl);

                var rangeEl = document.createElement('span');
                rangeEl.className = 'relay-hours-range';
                rangeEl.textContent = range;
                li.appendChild(rangeEl);

                hoursList.appendChild(li);
            });

            body.appendChild(hoursList);
        }

        label.appendChild(input);
        label.appendChild(body);

        return label;
    }

    function loadRelayPoints(postalCode, country) {
        if (!relayGrid || !config.relayPointsUrl || !postalCode) {
            return;
        }

        var token = ++relayRequestToken;

        fetch(config.relayPointsUrl + '?postal_code=' + encodeURIComponent(postalCode) + '&country=' + encodeURIComponent(country || 'FR'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (token !== relayRequestToken) {
                    return;
                }

                relayGrid.innerHTML = '';
                var points = data.points || [];

                points.forEach(function (point) {
                    relayGrid.appendChild(renderRelayPoint(point));
                });

                if (relayEmpty) {
                    relayEmpty.hidden = points.length > 0;
                }

                relayPointsLoadedFor = postalCode;
            })
            .catch(function () {});
    }

    document.querySelectorAll('input[name="address_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            relayPointsLoadedFor = null;

            if (isMondialRelay(selectedCarrier())) {
                loadRelayPoints(input.getAttribute('data-postal-code'), input.getAttribute('data-country'));
            }
        });
    });

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

    config.syncTotals = syncTotals;

    syncRelayPicker();
    syncTotals();
    syncBillingPicker();
})();
