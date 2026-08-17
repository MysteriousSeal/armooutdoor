(function () {
    var openWrap = null;

    function selector() {
        return 'select.form-control, select.sort-select, select.order-shipping-select';
    }

    function wrapFor(select) {
        return select.closest('.pretty-select, .sort-select-wrap, .order-shipping-select-wrap');
    }

    function closeOpen() {
        if (!openWrap) {
            return;
        }
        openWrap.classList.remove('is-open');
        var list = openWrap.querySelector('.pretty-select-list');
        if (list) {
            list.hidden = true;
        }
        openWrap = null;
    }

    function optionButtons(list) {
        return Array.prototype.slice.call(list.querySelectorAll('.pretty-select-option'));
    }

    function rebuild(select, list) {
        list.innerHTML = '';
        Array.prototype.forEach.call(select.options, function (option, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'pretty-select-option';
            button.setAttribute('data-index', String(index));
            button.textContent = option.textContent;
            if (option.disabled) {
                button.disabled = true;
            }
            if (option.selected) {
                button.classList.add('is-selected');
            }
            var item = document.createElement('li');
            item.appendChild(button);
            list.appendChild(item);
        });
    }

    function setActive(list, index) {
        var buttons = optionButtons(list);
        buttons.forEach(function (button, i) {
            button.classList.toggle('is-active', i === index);
        });
        var item = buttons[index];
        if (!item) {
            return;
        }
        var listRect = list.getBoundingClientRect();
        var itemRect = item.getBoundingClientRect();
        if (itemRect.bottom > listRect.bottom) {
            list.scrollTop += itemRect.bottom - listRect.bottom;
        } else if (itemRect.top < listRect.top) {
            list.scrollTop -= listRect.top - itemRect.top;
        }
    }

    function choose(select, index) {
        var option = select.options[index];
        if (!option || option.disabled) {
            return;
        }
        select.selectedIndex = index;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        closeOpen();
        select.focus();
    }

    function open(select, wrap, list) {
        rebuild(select, list);
        wrap.classList.add('is-open');
        list.hidden = false;
        openWrap = wrap;
        setActive(list, select.selectedIndex > -1 ? select.selectedIndex : 0);
    }

    function toggle(select, wrap, list) {
        if (wrap.classList.contains('is-open')) {
            closeOpen();
            return;
        }
        closeOpen();
        open(select, wrap, list);
    }

    function mount(select) {
        if (select.multiple || select.size > 1 || select.dataset.prettySelect === 'off' || select.dataset.prettySelectReady) {
            return;
        }

        var wrap = wrapFor(select);
        if (!wrap) {
            wrap = document.createElement('span');
            wrap.className = 'pretty-select';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);
        }

        var list = wrap.querySelector('.pretty-select-list');
        if (!list) {
            list = document.createElement('ul');
            list.className = 'pretty-select-list';
            list.hidden = true;
            wrap.appendChild(list);
        }

        select.dataset.prettySelectReady = '1';

        select.addEventListener('mousedown', function (event) {
            if (select.disabled || event.button !== 0) {
                return;
            }
            event.preventDefault();
            select.focus();
            toggle(select, wrap, list);
        });

        select.addEventListener('keydown', function (event) {
            if (select.disabled) {
                return;
            }

            var isOpen = wrap.classList.contains('is-open');
            var buttons = optionButtons(list);
            var current = buttons.findIndex(function (button) {
                return button.classList.contains('is-active');
            });
            if (current < 0) {
                current = select.selectedIndex > -1 ? select.selectedIndex : 0;
            }

            if (!isOpen && (event.key === ' ' || event.key === 'Enter' || event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                event.preventDefault();
                open(select, wrap, list);
                return;
            }

            if (!isOpen) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeOpen();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(list, Math.min(buttons.length - 1, current + 1));
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(list, Math.max(0, current - 1));
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var active = list.querySelector('.pretty-select-option.is-active') || list.querySelector('.pretty-select-option.is-selected');
                if (active) {
                    choose(select, parseInt(active.getAttribute('data-index'), 10));
                }
            }
        });

        list.addEventListener('mousedown', function (event) {
            var button = event.target.closest('.pretty-select-option');
            if (!button || button.disabled) {
                return;
            }
            event.preventDefault();
            choose(select, parseInt(button.getAttribute('data-index'), 10));
        });
    }

    document.addEventListener('mousedown', function (event) {
        if (openWrap && !openWrap.contains(event.target)) {
            closeOpen();
        }
    });

    document.addEventListener('scroll', function (event) {
        if (openWrap && openWrap.contains(event.target)) {
            return;
        }
        closeOpen();
    }, true);

    function mountAll(scope) {
        (scope || document).querySelectorAll(selector()).forEach(mount);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            mountAll();
        });
    } else {
        mountAll();
    }

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.matches && node.matches(selector())) {
                    mount(node);
                }
                if (node.querySelectorAll) {
                    node.querySelectorAll(selector()).forEach(mount);
                }
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });

    window.PrettySelect = { mount: mount, mountAll: mountAll };
})();
