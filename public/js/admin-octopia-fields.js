/**
 * The Cdiscount listing page: only the chosen category's fields are shown,
 * and only they are sent. The others are disabled rather than merely hidden,
 * so a category switched away from leaves nothing behind in what is posted.
 */
(function () {
    var select = document.getElementById('octopia_template_id');

    if (!select) {
        return;
    }

    var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-octopia-fields]'));
    var offer = document.querySelector('[data-octopia-offer]');

    function apply() {
        blocks.forEach(function (block) {
            var current = block.getAttribute('data-octopia-fields') === select.value;

            block.hidden = !current;
            block.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !current;
            });
        });

        // No category, no offer: nothing to sell it under.
        if (offer) {
            offer.hidden = select.value === '';
            offer.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = select.value === '';
            });
        }
    }

    select.addEventListener('change', apply);
    apply();

    // The price on Cdiscount, following the markup as it is typed. Rounded to
    // the cent the way the server rounds it when the offer is sent.
    var markup = document.querySelector('[data-offer-markup]');
    var euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

    if (markup) {
        var showPrices = function () {
            var rate = parseFloat(markup.value);

            document.querySelectorAll('[data-shop-cents]').forEach(function (price) {
                var cents = parseInt(price.getAttribute('data-shop-cents'), 10);

                price.textContent = euros.format(Math.round(cents * (1 + (isNaN(rate) ? 0 : rate) / 100)) / 100);
            });
        };

        markup.addEventListener('input', showPrices);
    }
})();
