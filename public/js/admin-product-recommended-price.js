(function () {
    var box = document.getElementById('supplier-recommended');
    var value = document.getElementById('supplier-recommended-value');
    var priceEl = document.getElementById('supplier_price');
    var markupEl = document.getElementById('markup_percent');

    if (!box || !value || !priceEl) {
        return;
    }

    var applyBtn = document.getElementById('supplier-recommended-apply');
    var modal = document.getElementById('apply-price-modal');
    var modalBody = document.getElementById('apply-price-body');
    var modalConfirm = document.getElementById('apply-price-confirm');
    var targetPriceEl = document.getElementById('price');

    var note = document.getElementById('supplier-recommended-note');
    var defaultNote = note ? note.textContent : '';
    var vatBasisPoints = parseInt(box.getAttribute('data-vat-basis-points'), 10);
    var maxPriceCents = parseInt(box.getAttribute('data-max-price-cents'), 10);

    if (isNaN(vatBasisPoints) || isNaN(maxPriceCents)) {
        return;
    }

    // Doit rester identique à Product::roundUpToPsychologicalPrice() : le
    // serveur rend la même valeur au chargement, un écart se verrait.
    function roundUp(cents) {
        var euros = Math.floor(cents / 100);
        var remainder = cents % 100;

        return remainder <= 49 ? euros * 100 + 49 : euros * 100 + 99;
    }

    function format(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    }

    // Le champ Prix vit dans un autre panneau, bien plus haut : sans un signe
    // visible, on ne sait pas si le clic a fait quelque chose.
    function highlight(el) {
        el.classList.remove('is-just-applied');
        void el.offsetWidth;
        el.classList.add('is-just-applied');
        setTimeout(function () {
            el.classList.remove('is-just-applied');
        }, 1600);
    }

    function currentRecommendationEuros() {
        return value.textContent.trim().replace(/\s|€/g, '').replace(',', '.');
    }

    function apply() {
        var euros = currentRecommendationEuros();

        targetPriceEl.value = euros;
        targetPriceEl.dispatchEvent(new Event('input', { bubbles: true }));
        highlight(targetPriceEl);

        if (window.armoToast) {
            window.armoToast.show('Price set to ' + value.textContent.trim() + ' — not saved yet.');
        }
    }

    function refresh() {
        var supplier = parseFloat(priceEl.value);

        // Zéro se traite comme une absence, comme côté serveur.
        if (isNaN(supplier) || supplier <= 0 || priceEl.value === '') {
            box.hidden = true;

            return;
        }

        var markup = parseFloat(markupEl ? markupEl.value : '');

        if (isNaN(markup) || markup < 0) {
            markup = 0;
        }

        var withVat = supplier * 100 * (1 + vatBasisPoints / 10000);
        var withMarkup = withVat * (1 + markup / 100);

        var recommended = roundUp(Math.ceil(Math.round(withMarkup * 10000) / 10000));
        var capped = recommended > maxPriceCents;

        value.textContent = format(capped ? maxPriceCents : recommended);
        box.hidden = false;

        if (note) {
            note.textContent = capped
                ? 'Capped at the maximum price the form accepts — the calculation came to ' + format(recommended) + '.'
                : defaultNote;
        }

        if (applyBtn && targetPriceEl) {
            applyBtn.hidden = false;
        }
    }

    if (applyBtn && targetPriceEl) {
        applyBtn.addEventListener('click', function () {
            var current = targetPriceEl.value.trim();

            // Un champ vide n'a rien à écraser : on remplit sans demander.
            if (current === '' || parseFloat(current) === parseFloat(currentRecommendationEuros())) {
                apply();

                return;
            }

            if (!modal || !modalBody || !modalConfirm) {
                apply();

                return;
            }

            modalBody.textContent = 'The price goes from ' + current.replace('.', ',') + ' € to '
                + value.textContent.trim() + '.';
            modal.showModal();
        });
    }

    if (modalConfirm) {
        modalConfirm.addEventListener('click', function () {
            if (modal) {
                modal.close();
            }

            apply();
        });
    }

    priceEl.addEventListener('input', refresh);

    if (markupEl) {
        markupEl.addEventListener('input', refresh);
    }

    refresh();
})();
