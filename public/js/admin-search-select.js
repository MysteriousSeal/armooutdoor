(function () {
    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function SearchSelect(root, options) {
        this.root = root;
        this.options = options || [];
        this.hidden = root.querySelector('input[type="hidden"]');
        this.input = root.querySelector('.search-select-input');
        this.list = root.querySelector('.search-select-list');
        this.activeIndex = -1;
        this.filtered = [];
        this.open = false;

        this.bind();
        this.syncFromHidden();
    }

    SearchSelect.prototype.bind = function () {
        var self = this;

        this.input.addEventListener('focus', function () {
            self.render(self.input.value);
            self.show();
        });

        this.input.addEventListener('input', function () {
            if (self.hidden.value !== '') {
                self.hidden.value = '';
            }
            self.render(self.input.value);
            self.show();
        });

        this.input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                self.move(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                self.move(-1);
            } else if (event.key === 'Enter') {
                if (self.open && self.filtered[self.activeIndex]) {
                    event.preventDefault();
                    self.choose(self.filtered[self.activeIndex]);
                }
            } else if (event.key === 'Escape') {
                self.hide();
                self.syncFromHidden();
            }
        });

        this.list.addEventListener('mousedown', function (event) {
            var button = event.target.closest('[data-id]');
            if (! button) {
                return;
            }
            event.preventDefault();
            var option = self.options.find(function (item) {
                return String(item.id) === String(button.getAttribute('data-id'));
            });
            if (option) {
                self.choose(option);
            }
        });

        document.addEventListener('mousedown', function (event) {
            if (! self.root.contains(event.target)) {
                self.hide();
                self.syncFromHidden();
            }
        });
    };

    SearchSelect.prototype.optionById = function (id) {
        return this.options.find(function (item) {
            return String(item.id) === String(id);
        }) || null;
    };

    SearchSelect.prototype.syncFromHidden = function () {
        var selected = this.optionById(this.hidden.value);
        this.input.value = selected ? selected.label : '';
    };

    SearchSelect.prototype.render = function (query) {
        var needle = normalize(query);
        var self = this;

        this.filtered = this.options.filter(function (item) {
            return needle === '' || normalize(item.search || item.label).indexOf(needle) !== -1;
        }).slice(0, 40);

        this.list.innerHTML = '';

        if (this.filtered.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'search-select-empty';
            empty.textContent = 'No matches';
            this.list.appendChild(empty);
            this.activeIndex = -1;
            return;
        }

        this.filtered.forEach(function (item, index) {
            var li = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-select-option';
            button.setAttribute('data-id', item.id);

            if (item.image !== undefined || item.sku !== undefined) {
                button.classList.add('search-select-option--rich');

                var thumb = document.createElement('span');
                thumb.className = 'search-select-thumb';
                if (item.image) {
                    var img = document.createElement('img');
                    img.src = item.image;
                    img.alt = '';
                    thumb.appendChild(img);
                }
                button.appendChild(thumb);

                var copy = document.createElement('span');
                copy.className = 'search-select-copy';

                var name = document.createElement('span');
                name.className = 'search-select-name';
                name.textContent = item.name || item.label;
                copy.appendChild(name);

                var sku = document.createElement('span');
                sku.className = 'search-select-sku';
                sku.textContent = item.sku ? 'SKU '+item.sku : 'No SKU';
                copy.appendChild(sku);

                if (item.meta) {
                    var meta = document.createElement('span');
                    meta.className = 'search-select-meta';
                    meta.textContent = item.meta;
                    copy.appendChild(meta);
                }

                button.appendChild(copy);
            } else if (item.email) {
                button.classList.add('search-select-option--person');

                var personCopy = document.createElement('span');
                personCopy.className = 'search-select-copy';

                var personName = document.createElement('span');
                personName.className = 'search-select-name';
                personName.textContent = item.name
                    || [item.first_name, item.last_name].filter(Boolean).join(' ')
                    || item.label;
                personCopy.appendChild(personName);

                var email = document.createElement('span');
                email.className = 'search-select-email';
                email.textContent = item.email;
                personCopy.appendChild(email);

                button.appendChild(personCopy);
            } else {
                button.textContent = item.label;
            }

            if (String(item.id) === String(self.hidden.value)) {
                button.classList.add('is-selected');
            }
            li.appendChild(button);
            self.list.appendChild(li);
            if (self.activeIndex === index) {
                button.classList.add('is-active');
            }
        });

        if (this.activeIndex >= this.filtered.length) {
            this.activeIndex = this.filtered.length - 1;
        }
    };

    SearchSelect.prototype.move = function (step) {
        if (! this.open) {
            this.render(this.input.value);
            this.show();
        }
        if (this.filtered.length === 0) {
            return;
        }
        this.activeIndex = (this.activeIndex + step + this.filtered.length) % this.filtered.length;
        this.render(this.input.value);
        var active = this.list.querySelector('.is-active');
        if (active) {
            active.scrollIntoView({ block: 'nearest' });
        }
    };

    SearchSelect.prototype.choose = function (option) {
        this.hidden.value = option.id;
        this.input.value = option.label;
        this.hidden.dispatchEvent(new CustomEvent('search-select:change', {
            bubbles: true,
            detail: option,
        }));
        this.hide();
    };

    SearchSelect.prototype.show = function () {
        this.open = true;
        this.root.classList.add('is-open');
        this.list.hidden = false;
    };

    SearchSelect.prototype.hide = function () {
        this.open = false;
        this.root.classList.remove('is-open');
        this.list.hidden = true;
        this.activeIndex = -1;
    };

    window.AdminSearchSelect = {
        catalogs: {},
        mount: function (root) {
            var source = root.getAttribute('data-source');
            var options = this.catalogs[source] || [];
            return new SearchSelect(root, options);
        },
        mountAll: function (scope) {
            (scope || document).querySelectorAll('[data-search-select]').forEach(function (root) {
                if (! root.dataset.searchSelectReady) {
                    root.dataset.searchSelectReady = '1';
                    window.AdminSearchSelect.mount(root);
                }
            });
        }
    };
})();
