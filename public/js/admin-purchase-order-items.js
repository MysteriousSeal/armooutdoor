(function () {
    var list = document.getElementById('po-items');
    var template = document.getElementById('po-item-row-template');
    var addButton = document.getElementById('po-add-item');

    if (!list || !template || !addButton) {
        return;
    }

    var nextIndex = list.querySelectorAll('.po-line').length;
    var shippingInput = document.getElementById('shipping_price');
    var discountInput = document.getElementById('discount_price');
    var additionalCostsInput = document.getElementById('additional_costs_price');

    function formatEuros(value) {
        return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20ac';
    }

    // Le total suit la frappe : une ligne dit ce qu'elle coûte, le pied de
    // panneau additionne lignes et port dans le mode de saisie courant.
    function updateTotals() {
        var linesTotal = 0;

        list.querySelectorAll('.po-line').forEach(function (row) {
            var qty = parseInt((row.querySelector('[data-item-qty]') || {}).value, 10) || 0;
            var cost = parseFloat((row.querySelector('[data-item-cost]') || {}).value);
            var target = row.querySelector('[data-line-total]');

            if (!target) {
                return;
            }

            if (qty > 0 && !isNaN(cost)) {
                var total = qty * cost;
                linesTotal += total;
                target.textContent = formatEuros(total);
            } else {
                target.textContent = '\u2014';
            }
        });

        var shipping = shippingInput ? parseFloat(shippingInput.value) || 0 : 0;
        var additionalCosts = additionalCostsInput ? parseFloat(additionalCostsInput.value) || 0 : 0;
        var discount = discountInput ? parseFloat(discountInput.value) || 0 : 0;

        var linesEl = document.querySelector('[data-total-lines]');
        var shippingEl = document.querySelector('[data-total-shipping]');
        var additionalEl = document.querySelector('[data-total-additional]');
        var discountEl = document.querySelector('[data-total-discount]');
        var grandEl = document.querySelector('[data-total-grand]');

        if (linesEl) linesEl.textContent = formatEuros(linesTotal);
        if (shippingEl) shippingEl.textContent = formatEuros(shipping);
        if (additionalEl) additionalEl.textContent = formatEuros(additionalCosts);
        if (discountEl) discountEl.textContent = formatEuros(discount);
        if (grandEl) grandEl.textContent = formatEuros(linesTotal + shipping + additionalCosts - discount);
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-item-qty], [data-item-cost], #shipping_price, #discount_price, #additional_costs_price')) {
            updateTotals();
        }
    });

    // Choisir un produit préremplit le coût d'achat et propose ses
    // déclinaisons ; le composant de recherche émet l'option choisie.
    list.addEventListener('search-select:change', function (event) {
        var row = event.target.closest('.po-line');
        var option = event.detail || {};

        if (!row) {
            return;
        }

        var cost = row.querySelector('[data-item-cost]');

        if (cost && option.cost !== undefined) {
            // Le prix d'achat de la fiche est HT : affiché dans le mode
            // courant, pour que la colonne dise partout la même chose.
            cost.value = option.cost === '' ? '' : toDisplay(parseFloat(option.cost));
        }

        var group = row.querySelector('[data-variant-group]');
        var select = row.querySelector('[data-item-variant]');

        if (group && select) {
            var variants = option.variants || [];

            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = variants.length > 0 ? '\u2014 Select a variant \u2014' : '\u2014';
            select.appendChild(placeholder);

            variants.forEach(function (variant) {
                var opt = document.createElement('option');
                opt.value = variant.id;
                opt.textContent = variant.label
                    + (variant.sku ? ' (' + variant.sku + ')' : '')
                    + ' \u2014 ' + variant.quantity + ' in stock';
                select.appendChild(opt);
            });

            // Estompée mais en place : la colonne des montants ne bouge pas.
            select.disabled = variants.length === 0;
            group.toggleAttribute('data-variant-empty', variants.length === 0);
        }

        updateTotals();
    });

    list.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-remove-item]');

        if (!remove) {
            return;
        }

        var rows = list.querySelectorAll('.po-line');

        // La dernière ligne reste : un bon de commande sans ligne n'a pas
        // de sens, et le formulaire la validerait de toute façon.
        if (rows.length > 1) {
            remove.closest('.po-line').remove();
            updateTotals();
        }
    });

    addButton.addEventListener('click', function () {
        var html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        var holder = document.createElement('div');
        holder.innerHTML = html;
        var row = holder.firstElementChild;
        list.appendChild(row);

        if (window.AdminSearchSelect) {
            window.AdminSearchSelect.mountAll(row);
        }

        // Le gabarit annonce HT en dur : la ligne neuve prend le mode courant.
        applyModeLabel();
        updateTotals();
    });

    // Un seul taux pour tout le formulaire : « ce que je tape est TTC à ce
    // taux ». Changer de taux convertit ce qui est déjà saisi, pour que les
    // champs continuent de dire ce que leur libellé annonce.
    var vatSelect = document.querySelector('[data-vat-rate]');
    var currentRate = vatSelect ? parseFloat(vatSelect.value) || 0 : 0;

    function toDisplay(exVat) {
        return (exVat * (1 + currentRate / 100)).toFixed(2);
    }

    function modeLabel() {
        return currentRate > 0 ? '(incl. VAT ' + currentRate + '%)' : '(excl. VAT)';
    }

    function applyModeLabel() {
        document.querySelectorAll('[data-cost-mode]').forEach(function (el) {
            el.textContent = modeLabel();
        });
    }

    if (vatSelect) {
        applyModeLabel();

        vatSelect.addEventListener('change', function () {
            var next = parseFloat(vatSelect.value) || 0;
            var factor = (1 + next / 100) / (1 + currentRate / 100);

            document.querySelectorAll('[data-item-cost], #shipping_price, #discount_price, #additional_costs_price').forEach(function (input) {
                if (input.value !== '') {
                    input.value = (parseFloat(input.value) * factor).toFixed(2);
                }
            });

            currentRate = next;
            applyModeLabel();
            updateTotals();
        });
    }

    // Le fournisseur connaît son délai : changer de fournisseur propose une
    // date d'arrivée, sans jamais écraser une date saisie à la main.
    var supplierSelect = document.querySelector('[data-supplier-select]');
    var expectedAt = document.querySelector('[data-expected-at]');
    var expectedTouched = expectedAt ? expectedAt.value !== '' : false;

    if (expectedAt) {
        expectedAt.addEventListener('input', function () {
            expectedTouched = true;
        });
    }

    if (supplierSelect && expectedAt) {
        supplierSelect.addEventListener('change', function () {
            if (expectedTouched) {
                return;
            }

            var option = supplierSelect.selectedOptions[0];
            var leadTime = option ? parseInt(option.getAttribute('data-lead-time'), 10) : NaN;

            if (isNaN(leadTime)) {
                return;
            }

            var date = new Date();
            date.setDate(date.getDate() + leadTime);
            expectedAt.value = date.toISOString().slice(0, 10);
        });
    }
    updateTotals();
})();
