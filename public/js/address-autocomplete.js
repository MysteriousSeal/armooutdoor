/*
 * Address suggestions under the « Adresse » field: from three characters,
 * the Base Adresse Nationale's matches (through the shop's own endpoint)
 * appear in a list; picking one fills the street, postal code and city.
 * Typing on without picking leaves a plain field, so a slow or failing
 * service never stands in the way.
 */
(function () {
    var MIN_LENGTH = 3;
    var DELAY_MS = 250;

    document.querySelectorAll('input[data-address-autocomplete]').forEach(setUp);

    function setUp(input) {
        var url = input.getAttribute('data-address-autocomplete');
        var form = input.form;
        var wrap = input.parentElement;
        var list = document.createElement('ul');
        var timer = null;
        var token = 0;
        var results = [];
        var active = -1;

        if (!url || !form || !wrap) {
            return;
        }

        wrap.classList.add('address-autocomplete');
        list.className = 'relay-search-results address-suggestions';
        list.id = input.id + '-suggestions';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrap.appendChild(list);

        // The browser's own dropdown (what was typed before, saved
        // addresses) would sit on top of this one. Turned off here rather
        // than in the markup, so without JavaScript the field keeps it.
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            results = [];
            active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function setField(name, value) {
            var field = form.querySelector('[name="' + name + '"]');

            if (!field) {
                return;
            }

            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function choose(result) {
            input.value = result.line1;
            setField('postal_code', result.postal_code);
            setField('city', result.city);
            setField('country', 'FR');
            close();

            // The complement (building, floor) is what is left to type.
            var line2 = form.querySelector('[name="line2"]');

            if (line2) {
                line2.focus();
            }
        }

        function highlight(index) {
            var options = list.querySelectorAll('[role="option"]');

            options.forEach(function (option, i) {
                option.classList.toggle('is-active', i === index);
                option.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            active = index;

            if (options[index]) {
                input.setAttribute('aria-activedescendant', options[index].id);
            }
        }

        function render(items) {
            list.innerHTML = '';
            results = items;
            active = -1;

            if (!items.length) {
                close();
                return;
            }

            items.forEach(function (result, i) {
                var li = document.createElement('li');
                var option = document.createElement('button');
                var street = document.createElement('span');
                var town = document.createElement('span');

                option.type = 'button';
                option.id = list.id + '-' + i;
                option.className = 'relay-search-option address-suggestion';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.tabIndex = -1;
                street.className = 'address-suggestion-street';
                street.textContent = result.line1;
                town.className = 'address-suggestion-town';
                town.textContent = result.postal_code + ' ' + result.city;
                option.appendChild(street);
                option.appendChild(town);

                // mousedown, not click: it fires before the field's blur
                // closes the list.
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(result);
                });

                li.appendChild(option);
                list.appendChild(li);
            });

            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();

            clearTimeout(timer);

            // The national address register covers France only.
            var country = form.querySelector('[name="country"]');

            if (query.length < MIN_LENGTH || (country && country.value && country.value !== 'FR')) {
                token++;
                close();
                return;
            }

            timer = setTimeout(function () {
                var mine = ++token;

                fetch(url + '?q=' + encodeURIComponent(query), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (response) { return response.ok ? response.json() : { results: [] }; })
                    .then(function (data) {
                        if (mine === token && document.activeElement === input) {
                            render(data.results || []);
                        }
                    })
                    .catch(function () {});
            }, DELAY_MS);
        });

        input.addEventListener('keydown', function (event) {
            if (list.hidden || !results.length) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight((active + 1) % results.length);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(active <= 0 ? results.length - 1 : active - 1);
            } else if (event.key === 'Enter' && active >= 0) {
                // Picks the highlighted address instead of submitting the form.
                event.preventDefault();
                choose(results[active]);
            } else if (event.key === 'Escape') {
                close();
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(close, 150);
        });
    }
})();
