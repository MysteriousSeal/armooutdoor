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
    var assist = document.querySelector('[data-octopia-assist]');

    function apply() {
        blocks.forEach(function (block) {
            var current = block.getAttribute('data-octopia-fields') === select.value;

            block.hidden = !current;
            block.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !current;
            });
        });

        // Nothing to fill before a category is chosen.
        if (assist) {
            assist.hidden = select.value === '';
        }

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

    // Claude fills the category's attributes from the product sheet. It
    // proposes: only the fields still empty are written, marked so they can be
    // told from what was typed, and nothing is saved until the form is.
    var run = document.querySelector('[data-octopia-generate]');
    var status = document.querySelector('[data-octopia-generate-status]');
    var token = document.querySelector('meta[name="csrf-token"]');

    function say(message, failed) {
        if (!status) {
            return;
        }

        status.hidden = !message;
        status.textContent = message || '';
        status.className = 'vinted-assist-status' + (failed ? ' is-failed' : '');
    }

    /** The attribute code out of a field name such as values[46831]. */
    function codeOf(field) {
        var match = /\[([^\]]+)\]$/.exec(field.getAttribute('name') || '');

        return match ? match[1] : null;
    }

    /** Write a proposed value into an empty field, if the field can take it. */
    function propose(field, value) {
        if (!field || field.value.trim() !== '') {
            return false;
        }

        if (field.tagName === 'SELECT') {
            var known = Array.prototype.some.call(field.options, function (option) {
                return option.value === value;
            });

            if (!known) {
                return false;
            }
        }

        field.value = value;
        field.classList.add('is-suggested');
        // Typing over a suggestion makes it the seller's own answer again.
        field.addEventListener('input', function () {
            field.classList.remove('is-suggested');
        }, { once: true });

        return true;
    }

    if (run) {
        var idle = run.textContent;

        run.addEventListener('click', function () {
            var block = document.querySelector('[data-octopia-fields="' + select.value + '"]');

            if (!block) {
                return;
            }

            var perVariant = Array.prototype.map.call(
                block.querySelectorAll('input[name="per_variant[]"]:checked'),
                function (box) { return box.value; }
            );

            // What is already answered stays: it is not asked for again.
            var skip = [];

            block.querySelectorAll('[name^="values["]').forEach(function (field) {
                if (field.value.trim() !== '') {
                    skip.push(codeOf(field));
                }
            });

            run.disabled = true;
            run.textContent = 'Reading…';
            say('Claude is reading the product sheet, a few seconds.', false);

            fetch(run.getAttribute('data-generate-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ octopia_template_id: select.value, per_variant: perVariant, skip: skip })
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw new Error(body && body.message ? body.message : 'Claude could not be reached.');
                    }

                    return body;
                });
            }).then(function (body) {
                var filled = 0;

                Object.keys(body.values || {}).forEach(function (code) {
                    if (propose(block.querySelector('[name="values[' + code + ']"]'), body.values[code])) {
                        filled++;
                    }
                });

                Object.keys(body.variants || {}).forEach(function (id) {
                    Object.keys(body.variants[id]).forEach(function (code) {
                        if (propose(document.querySelector('[name="variants[' + id + '][' + code + ']"]'), body.variants[id][code])) {
                            filled++;
                        }
                    });
                });

                // What Octopia requires and is still empty is what the seller
                // has left to do: named, not left to be found on Save.
                var lacking = [];

                block.querySelectorAll('.octopia-field').forEach(function (group) {
                    var field = group.querySelector('[name^="values["]');

                    if (field && group.querySelector('.octopia-required') && field.value.trim() === '') {
                        lacking.push(group.querySelector('label').textContent.replace('*', '').trim());
                    }
                });

                var message = filled === 0
                    ? 'Nothing more could be established from the product sheet.'
                    : 'Filled ' + filled + (filled === 1 ? ' attribute' : ' attributes') + ' from the product sheet. Read them over, then save.';

                if (lacking.length) {
                    message += ' Still to answer, and required: ' + lacking.join(', ') + '.';
                }

                if (perVariant.length && !document.querySelector('[name^="variants["]')) {
                    message += ' Save the listing first to fill the variants.';
                }

                say(message, false);
            }).catch(function (error) {
                say(error.message || 'Claude could not be reached.', true);
            }).then(function () {
                run.disabled = false;
                run.textContent = idle;
            });
        });
    }
})();
