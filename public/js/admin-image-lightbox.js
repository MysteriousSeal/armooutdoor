/**
 * A fullscreen image viewer for the admin, modelled on the shop's product
 * lightbox (product-gallery.js): arrows and ←/→ step through the set it was
 * opened with, Esc or a click beside the image closes it.
 *
 * Usage: window.armoImageLightbox.open(['url1', 'url2'], startIndex)
 */
(function () {
    var box = null;
    var image = null;
    var counter = null;
    var prev = null;
    var next = null;
    var sources = [];
    var current = 0;
    var returnFocusTo = null;

    // Font Awesome icons (CC BY 4.0), as in product-gallery.js.
    var CLOSE_ICON = '<svg viewBox="0 0 384 512" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>';
    var PREV_ICON = '<svg viewBox="0 0 320 512" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></svg>';
    var NEXT_ICON = '<svg viewBox="0 0 320 512" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L233.4 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></svg>';

    function iconButton(className, label, icon) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('aria-label', label);
        button.innerHTML = icon;

        return button;
    }

    // Built on first use: most visits to the page never open it.
    function build() {
        box = document.createElement('div');
        box.className = 'admin-lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', 'Image preview');
        box.hidden = true;

        var close = iconButton('admin-lightbox-close', 'Close', CLOSE_ICON);
        prev = iconButton('admin-lightbox-nav admin-lightbox-prev', 'Previous image', PREV_ICON);
        next = iconButton('admin-lightbox-nav admin-lightbox-next', 'Next image', NEXT_ICON);

        var stage = document.createElement('figure');
        stage.className = 'admin-lightbox-stage';

        image = document.createElement('img');
        image.className = 'admin-lightbox-image';
        image.alt = '';
        stage.appendChild(image);

        counter = document.createElement('p');
        counter.className = 'admin-lightbox-counter';
        counter.setAttribute('aria-live', 'polite');

        box.append(close, prev, next, stage, counter);
        document.body.appendChild(box);

        close.addEventListener('click', closeBox);
        prev.addEventListener('click', function () { show(current - 1); });
        next.addEventListener('click', function () { show(current + 1); });

        box.addEventListener('click', function (event) {
            if (event.target === box || event.target === stage) {
                closeBox();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (box.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeBox();
            } else if (event.key === 'ArrowLeft' && sources.length > 1) {
                event.preventDefault();
                show(current - 1);
            } else if (event.key === 'ArrowRight' && sources.length > 1) {
                event.preventDefault();
                show(current + 1);
            }
        });

        // Swipe on touch screens: a mostly-horizontal move of 40px+ turns the page.
        var touchX = null;
        var touchY = null;

        box.addEventListener('touchstart', function (event) {
            touchX = event.changedTouches[0].clientX;
            touchY = event.changedTouches[0].clientY;
        }, { passive: true });

        box.addEventListener('touchend', function (event) {
            if (touchX === null || sources.length < 2) {
                return;
            }

            var dx = event.changedTouches[0].clientX - touchX;
            var dy = event.changedTouches[0].clientY - touchY;
            touchX = touchY = null;

            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                show(dx > 0 ? current - 1 : current + 1);
            }
        }, { passive: true });
    }

    function show(index) {
        current = (index + sources.length) % sources.length;
        image.src = sources[current];
        counter.textContent = (current + 1) + ' / ' + sources.length;
    }

    function openBox(urls, index) {
        sources = (urls || []).filter(Boolean);

        if (sources.length === 0) {
            return;
        }

        if (!box) {
            build();
        }

        var several = sources.length > 1;
        prev.hidden = !several;
        next.hidden = !several;
        counter.hidden = !several;

        returnFocusTo = document.activeElement;
        box.hidden = false;
        document.body.classList.add('admin-lightbox-open');
        show(Math.max(0, Math.min(index || 0, sources.length - 1)));
        box.querySelector('.admin-lightbox-close').focus();
    }

    function closeBox() {
        box.hidden = true;
        document.body.classList.remove('admin-lightbox-open');

        if (returnFocusTo && returnFocusTo.focus) {
            returnFocusTo.focus();
        }
    }

    window.armoImageLightbox = { open: openBox };
})();
