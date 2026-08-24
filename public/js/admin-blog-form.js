(function () {
    var picker = document.querySelector('.blog-product-picker');
    var coverSlot = document.querySelector('.blog-cover-slot');

    if (coverSlot) {
        var coverInput = coverSlot.querySelector('input[type="file"]');
        var coverEmpty = coverSlot.querySelector('.blog-cover-empty-title');

        if (coverInput) {
            coverInput.addEventListener('change', function () {
                var file = coverInput.files && coverInput.files[0];

                if (!file) {
                    return;
                }

                var preview = coverSlot.querySelector('.blog-cover-preview');

                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'blog-cover-preview';
                    preview.alt = '';
                    preview.width = 800;
                    preview.height = 450;
                    coverSlot.insertBefore(preview, coverSlot.firstChild);
                }

                preview.src = URL.createObjectURL(file);
                coverSlot.classList.add('has-image');

                if (coverEmpty) {
                    coverEmpty.textContent = 'Replace the cover';
                }
            });
        }
    }

    if (!picker) {
        return;
    }

    var input = picker.querySelector('.blog-product-search');
    var results = picker.querySelector('.blog-product-results');
    var selected = picker.querySelector('.blog-product-selected');
    var products = [];

    try {
        products = JSON.parse(picker.dataset.products || '[]');
    } catch (error) {
        products = [];
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '');
    }

    function chosenIds() {
        return Array.prototype.map.call(selected.querySelectorAll('li'), function (row) {
            return String(row.dataset.id);
        });
    }

    function closeResults() {
        results.hidden = true;
        results.innerHTML = '';
    }

    function add(product) {
        if (chosenIds().indexOf(String(product.id)) !== -1) {
            return;
        }

        var row = document.createElement('li');
        row.dataset.id = product.id;

        var copy = document.createElement('span');
        copy.className = 'blog-product-copy';

        var name = document.createElement('span');
        name.className = 'blog-product-name';
        name.textContent = product.label;
        copy.appendChild(name);

        if (product.sku) {
            var sku = document.createElement('span');
            sku.className = 'blog-product-sku';
            sku.textContent = product.sku;
            copy.appendChild(sku);
        }

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'product_ids[]';
        hidden.value = product.id;

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'blog-product-remove';
        remove.dataset.remove = '';
        remove.setAttribute('aria-label', 'Remove');
        remove.innerHTML = '&times;';

        row.append(copy, hidden, remove);
        selected.appendChild(row);
    }

    input.addEventListener('input', function () {
        var term = normalize(input.value.trim());

        if (term.length < 2) {
            closeResults();

            return;
        }

        var taken = chosenIds();
        var matches = products
            .filter(function (product) {
                return taken.indexOf(String(product.id)) === -1
                    && (normalize(product.label).indexOf(term) !== -1
                        || normalize(product.sku).indexOf(term) !== -1);
            })
            .slice(0, 8);

        results.innerHTML = '';

        if (matches.length === 0) {
            closeResults();

            return;
        }

        matches.forEach(function (product) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';

            var label = document.createElement('span');
            label.className = 'blog-product-name';
            label.textContent = product.label;
            button.appendChild(label);

            if (product.sku) {
                var sku = document.createElement('span');
                sku.className = 'blog-product-sku';
                sku.textContent = product.sku;
                button.appendChild(sku);
            }

            button.addEventListener('click', function () {
                add(product);
                input.value = '';
                closeResults();
            });
            item.appendChild(button);
            results.appendChild(item);
        });

        results.hidden = false;
    });

    selected.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove]');

        if (button) {
            button.closest('li').remove();
        }
    });

    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) {
            closeResults();
        }
    });
})();
