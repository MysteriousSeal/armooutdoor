(function () {
    var config = window.armoCheckout;
    var picker = document.getElementById('relay-picker');
    var payment = document.getElementById('payment-section');
    var paymentLockedHint = document.getElementById('payment-locked-hint');
    var search = document.getElementById('relay-search');
    var shippingPrice = document.getElementById('checkout-shipping-price');
    var grandTotal = document.getElementById('checkout-grand-total');
    var sameBilling = document.getElementById('same-billing-address');
    var billingPicker = document.getElementById('billing-address-picker');
    var submitButton = document.getElementById('checkout-submit');

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

    var RELAY_PROVIDERS = {
        'mondial-relay': 'mondial_relay',
        'relais-pickup': 'chronopost',
    };
    var relayPointsLoadedFor = null;

    function relayProvider(carrier) {
        if (!carrier) {
            return null;
        }

        return RELAY_PROVIDERS[carrier.getAttribute('data-carrier-slug')] || null;
    }

    function syncRelayPicker() {
        var carrier = selectedCarrier();
        var isRelay = carrier && carrier.getAttribute('data-method') === 'relay';
        var provider = relayProvider(carrier);
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

        if (!provider) {
            relayPointsLoadedFor = null;
            if (relayGrid) {
                relayGrid.innerHTML = '';
            }
            if (relayEmpty) {
                relayEmpty.hidden = !isRelay;
            }
            if (relayMoreButton) {
                relayMoreButton.hidden = true;
                relayMoreButton.removeAttribute('data-pending');
            }
            hideRelaySelection();
            return;
        }

        var address = selectedAddress();
        var postalCode = address ? address.getAttribute('data-postal-code') : null;
        var cacheKey = provider + ':' + postalCode;

        if (postalCode && cacheKey !== relayPointsLoadedFor) {
            loadRelayPoints(postalCode, address.getAttribute('data-country'), provider);
        }
    }

    function syncPaymentAvailability() {
        var carrier = selectedCarrier();
        var isRelay = carrier && carrier.getAttribute('data-method') === 'relay';
        var hasRelayPoint = !!document.querySelector('input[name="relay_point_id"]:checked');
        var locked = !!isRelay && !hasRelayPoint;

        document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
            input.disabled = locked;
            if (locked) {
                input.checked = false;
            }
        });

        if (payment) {
            payment.classList.toggle('is-locked', locked);
        }

        if (paymentLockedHint) {
            paymentLockedHint.hidden = !locked;
        }
    }

    function syncSubmitAvailability() {
        if (!submitButton) {
            return;
        }

        var hasAddress = !!document.querySelector('input[name="address_id"]:checked');
        var carrier = selectedCarrier();
        var isRelay = carrier && carrier.getAttribute('data-method') === 'relay';
        var hasRelayPoint = !!document.querySelector('input[name="relay_point_id"]:checked');
        var hasBilling = !sameBilling || sameBilling.checked
            || !!document.querySelector('input[name="billing_address_id"]:checked');
        var hasPayment = !!document.querySelector('input[name="payment_method"]:checked');

        var ready = hasAddress
            && !!carrier
            && (!isRelay || hasRelayPoint)
            && hasBilling
            && hasPayment;

        submitButton.disabled = !ready;
    }

    function syncTotals() {
        var carrier = selectedCarrier();
        var shippingCents = carrier ? (config.carriers[carrier.value] || 0) : 0;

        // A free-relay-delivery code only bites once a covered carrier is
        // picked, so this is recomputed on every carrier change.
        var waived = config.freeShippingCarrierIds || [];
        var shippingDiscount = (carrier && waived.indexOf(parseInt(carrier.value, 10)) !== -1)
            ? shippingCents
            : 0;

        if (shippingPrice) {
            shippingPrice.textContent = carrier
                ? (shippingCents === 0 ? 'Gratuite' : formatEuros(shippingCents))
                : '—';
        }

        var discountRow = document.getElementById('checkout-shipping-discount-row');
        var discountValue = document.getElementById('checkout-shipping-discount-value');
        if (discountRow) {
            discountRow.hidden = shippingDiscount === 0;
        }
        if (discountValue) {
            discountValue.textContent = '-' + formatEuros(shippingDiscount);
        }

        if (grandTotal) {
            grandTotal.textContent = formatEuros(
                config.subtotalCents - (config.discountCents || 0) + shippingCents - shippingDiscount
            );
        }
    }

    document.querySelectorAll('input[name="carrier_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            syncRelayPicker();
            syncTotals();
            syncPaymentAvailability();
            syncSubmitAvailability();
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

                var provider = relayProvider(selectedCarrier());

                if (provider) {
                    loadRelayPoints(pair[0], 'FR', provider);
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
    var relayMoreButton = document.getElementById('relay-points-more');
    var relayRequestToken = 0;
    var RELAY_PAGE_SIZE = 10;

    if (relayMoreButton) {
        relayMoreButton.addEventListener('click', function () {
            var pending = JSON.parse(relayMoreButton.getAttribute('data-pending') || '[]');
            var next = pending.splice(0, RELAY_PAGE_SIZE);

            next.forEach(function (point) {
                relayGrid.appendChild(renderRelayPoint(point));
            });

            if (pending.length > 0) {
                relayMoreButton.setAttribute('data-pending', JSON.stringify(pending));
            } else {
                relayMoreButton.hidden = true;
                relayMoreButton.removeAttribute('data-pending');
            }
        });
    }

    function buildRelayBody(point) {
        var body = document.createElement('span');
        body.className = 'choice-card-body';

        var title = document.createElement('span');
        title.className = 'choice-card-title relay-title';
        title.textContent = point.name;
        body.appendChild(title);

        var line1 = document.createElement('span');
        line1.className = 'choice-card-meta relay-address';
        line1.textContent = point.line1;
        body.appendChild(line1);

        var cityLine = document.createElement('span');
        cityLine.className = 'choice-card-meta relay-address';
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

        return body;
    }

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

        label.appendChild(input);
        label.appendChild(buildRelayBody(point));

        return label;
    }

    var relayList = document.getElementById('relay-list');
    var relaySelected = document.getElementById('relay-selected');
    var relaySelectedBody = document.getElementById('relay-selected-body');
    var relaySelectedChange = document.getElementById('relay-selected-change');
    var relayPointError = document.getElementById('relay-point-error');

    function clearRelayPointError() {
        if (relayPointError) {
            relayPointError.hidden = true;
        }
    }

    function showRelaySelectionFromLabel(label) {
        var body = label.querySelector('.choice-card-body');

        if (!body || !relaySelectedBody || !relayList || !relaySelected) {
            return;
        }

        relaySelectedBody.innerHTML = body.innerHTML;
        relayList.hidden = true;
        relaySelected.hidden = false;
        clearRelayPointError();
    }

    function showRelaySelectionFromPoint(point) {
        if (!relaySelectedBody || !relayList || !relaySelected) {
            return;
        }

        relaySelectedBody.innerHTML = '';
        relaySelectedBody.appendChild(buildRelayBody(point));
        relayList.hidden = true;
        relaySelected.hidden = false;
        clearRelayPointError();
    }

    function hideRelaySelection() {
        if (!relayList || !relaySelected) {
            return;
        }

        document.querySelectorAll('input[name="relay_point_id"]').forEach(function (input) {
            input.checked = false;
        });
        config.selectedRelayPointId = null;

        relaySelected.hidden = true;
        relayList.hidden = false;
    }

    document.addEventListener('change', function (event) {
        if (event.target.name === 'relay_point_id' && event.target.checked) {
            var label = event.target.closest('.relay-option');
            if (label) {
                showRelaySelectionFromLabel(label);
            }
            syncPaymentAvailability();
            syncSubmitAvailability();
        }
    });

    if (relayGrid) {
        relayGrid.addEventListener('click', function (event) {
            var option = event.target.closest('.relay-option');
            if (!option) {
                return;
            }
            var input = option.querySelector('input[name="relay_point_id"]');
            if (input && input.checked && !input.disabled) {
                showRelaySelectionFromLabel(option);
            }
        });
    }

    if (relaySelectedChange) {
        relaySelectedChange.addEventListener('click', function () {
            hideRelaySelection();
            syncPaymentAvailability();
            syncSubmitAvailability();
        });
    }

    function loadRelayPoints(postalCode, country, provider) {
        if (!relayGrid || !config.relayPointsUrl || !postalCode) {
            return;
        }

        provider = provider || 'mondial_relay';

        var token = ++relayRequestToken;
        var url = config.relayPointsUrl
            + '?postal_code=' + encodeURIComponent(postalCode)
            + '&country=' + encodeURIComponent(country || 'FR')
            + '&provider=' + encodeURIComponent(provider);

        fetch(url, {
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
                var firstPage = points.slice(0, RELAY_PAGE_SIZE);
                var rest = points.slice(RELAY_PAGE_SIZE);

                firstPage.forEach(function (point) {
                    relayGrid.appendChild(renderRelayPoint(point));
                });

                if (relayEmpty) {
                    relayEmpty.hidden = points.length > 0;
                }

                if (relayMoreButton) {
                    if (rest.length > 0) {
                        relayMoreButton.setAttribute('data-pending', JSON.stringify(rest));
                        relayMoreButton.hidden = false;
                    } else {
                        relayMoreButton.hidden = true;
                        relayMoreButton.removeAttribute('data-pending');
                    }
                }

                var preselected = config.selectedRelayPointId !== null
                    ? points.filter(function (point) {
                        return String(point.id) === String(config.selectedRelayPointId);
                    })[0]
                    : null;

                if (preselected) {
                    showRelaySelectionFromPoint(preselected);
                } else {
                    hideRelaySelection();
                }

                syncPaymentAvailability();
                syncSubmitAvailability();

                relayPointsLoadedFor = provider + ':' + postalCode;
            })
            .catch(function () {});
    }

    document.querySelectorAll('input[name="address_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            relayPointsLoadedFor = null;

            var provider = relayProvider(selectedCarrier());

            if (provider) {
                loadRelayPoints(input.getAttribute('data-postal-code'), input.getAttribute('data-country'), provider);
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

    document.addEventListener('change', function (event) {
        var name = event.target.name;

        if (name === 'address_id' || name === 'billing_address_id' || name === 'payment_method' || event.target === sameBilling) {
            syncSubmitAvailability();
        }
    });

    config.syncTotals = syncTotals;

    syncRelayPicker();
    syncTotals();
    syncBillingPicker();
    syncPaymentAvailability();
    syncSubmitAvailability();
})();
