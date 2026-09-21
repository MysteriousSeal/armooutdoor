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
    var describe = document.querySelector('[data-octopia-description]');

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

        // No category, no description to write for it.
        if (describe) {
            describe.hidden = select.value === '';
            describe.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = select.value === '';
            });
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

    /**
     * Write a proposed value into an empty field, if the field can take it.
     * A value Claude assumed rather than read is marked apart, so that it is
     * checked rather than taken for a fact of the sheet.
     */
    function propose(field, value, assumed) {
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
        field.classList.add(assumed ? 'is-assumed' : 'is-suggested');

        // A suggestion in a closed group would go unseen: open it.
        var group = field.closest('details');

        if (group) {
            group.open = true;
        }

        var tag = null;

        if (assumed && field.parentNode) {
            tag = document.createElement('span');
            tag.className = 'octopia-assumed-tag';
            tag.textContent = 'Assumed, check it';
            field.parentNode.insertBefore(tag, field);
        }

        // Typing over a suggestion makes it the seller's own answer again.
        field.addEventListener('input', function () {
            field.classList.remove('is-suggested', 'is-assumed');

            if (tag && tag.parentNode) {
                tag.parentNode.removeChild(tag);
            }
        }, { once: true });

        return true;
    }

    // The description for Cdiscount: written by Claude for the chosen
    // category, and counted as it is typed.
    var text = document.querySelector('[data-octopia-description-field]');
    var count = document.querySelector('[data-octopia-count]');
    var write = document.querySelector('[data-octopia-describe]');
    var writeStatus = document.querySelector('[data-octopia-describe-status]');

    function counted() {
        if (text && count) {
            count.textContent = String(text.value.length);
        }
    }

    if (text) {
        text.addEventListener('input', function () {
            text.classList.remove('is-suggested');
            counted();
        });
    }

    if (write && text) {
        var writeIdle = write.textContent;

        function tell(message, failed) {
            if (!writeStatus) {
                return;
            }

            writeStatus.hidden = !message;
            writeStatus.textContent = message || '';
            writeStatus.className = 'vinted-assist-status' + (failed ? ' is-failed' : '');
        }

        write.addEventListener('click', function () {
            // What is already written was written by somebody: it is not
            // replaced without being asked for.
            if (text.value.trim() !== '' && !window.confirm('Replace what is already written?')) {
                return;
            }

            write.disabled = true;
            write.textContent = 'Writing…';
            tell('Claude is reading the product sheet, a few seconds.', false);

            fetch(write.getAttribute('data-describe-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ octopia_template_id: select.value })
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw new Error(body && body.message ? body.message : 'Claude could not be reached.');
                    }

                    return body;
                });
            }).then(function (body) {
                text.value = body.description || '';
                text.classList.add('is-suggested');
                counted();
                tell('Written for the category chosen. Read it over, then save.', false);
            }).catch(function (error) {
                tell(error.message || 'Claude could not be reached.', true);
            }).then(function () {
                write.disabled = false;
                write.textContent = writeIdle;
            });
        });
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
                var assumedLabels = [];
                var assumed = body.assumed || [];

                Object.keys(body.values || {}).forEach(function (code) {
                    var field = block.querySelector('[name="values[' + code + ']"]');
                    var guess = assumed.indexOf(code) !== -1;

                    if (propose(field, body.values[code], guess)) {
                        filled++;

                        if (guess) {
                            assumedLabels.push(field.closest('.octopia-field').querySelector('label').textContent.replace('*', '').trim());
                        }
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

                // Told apart in the message too: these are not read off the sheet.
                if (assumedLabels.length) {
                    message += ' Assumed rather than read from the sheet, so check them: ' + assumedLabels.join(', ') + '.';
                }

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
