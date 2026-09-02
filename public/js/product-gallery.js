(function () {
    var mainImage = document.getElementById('product-detail-main-image');

    if (!mainImage) {
        return;
    }

    var thumbs = Array.prototype.slice.call(document.querySelectorAll('.product-detail-thumb'));

    // The gallery as [{full, thumb}], read from the thumbnails — or just
    // the main photo when the product has no gallery.
    var slides = thumbs.length
        ? thumbs.map(function (thumb) {
            return { full: thumb.dataset.fullSrc, thumb: thumb.querySelector('img').src };
        })
        : [{ full: mainImage.src, thumb: mainImage.src }];

    thumbs.forEach(function (thumb, index) {
        thumb.addEventListener('click', function () {
            var src = thumb.dataset.fullSrc;

            if (!src) {
                return;
            }

            mainImage.src = src;
            current = index;

            thumbs.forEach(function (other) {
                other.classList.toggle('is-active', other === thumb);
            });
        });
    });

    /* --- Lightbox ------------------------------------------------------ */

    var current = 0;
    var open = false;
    var returnFocusTo = null;

    var box = document.createElement('div');
    box.className = 'lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', mainImage.alt);
    box.hidden = true;
    box.innerHTML =
        // Font Awesome's xmark (CC BY 4.0), inlined like partials/icon.blade.php does.
        '<button type="button" class="lightbox-close" aria-label="Fermer">' +
        '<svg viewBox="0 0 384 512" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>' +
        '</button>' +
        // Font Awesome chevrons (CC BY 4.0), like the close button's xmark.
        (slides.length > 1
            ? '<button type="button" class="lightbox-nav lightbox-prev" aria-label="Photo précédente">' +
              '<svg viewBox="0 0 320 512" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></svg>' +
              '</button>' +
              '<button type="button" class="lightbox-nav lightbox-next" aria-label="Photo suivante">' +
              '<svg viewBox="0 0 320 512" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L233.4 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></svg>' +
              '</button>'
            : '') +
        '<figure class="lightbox-stage"><img class="lightbox-image" alt="' + mainImage.alt.replace(/"/g, '&quot;') + '"></figure>' +
        (slides.length > 1
            ? '<div class="lightbox-foot">' +
              '<span class="lightbox-counter" aria-live="polite"></span>' +
              '<div class="lightbox-thumbs">' +
              slides.map(function (slide, i) {
                  return '<button type="button" class="lightbox-thumb" data-index="' + i + '"><img src="' + slide.thumb + '" alt="" loading="lazy"></button>';
              }).join('') +
              '</div></div>'
            : '');
    document.body.appendChild(box);

    var image = box.querySelector('.lightbox-image');
    var counter = box.querySelector('.lightbox-counter');
    var boxThumbs = Array.prototype.slice.call(box.querySelectorAll('.lightbox-thumb'));

    function show(index) {
        current = (index + slides.length) % slides.length;
        image.src = slides[current].full;

        if (counter) {
            counter.textContent = (current + 1) + ' / ' + slides.length;
        }

        boxThumbs.forEach(function (thumb, i) {
            thumb.classList.toggle('is-active', i === current);
        });
    }

    function openBox(index) {
        returnFocusTo = document.activeElement;
        open = true;
        box.hidden = false;
        document.body.classList.add('lightbox-open');
        show(index);
        box.querySelector('.lightbox-close').focus();
    }

    function closeBox() {
        open = false;
        box.hidden = true;
        document.body.classList.remove('lightbox-open');

        if (returnFocusTo && returnFocusTo.focus) {
            returnFocusTo.focus();
        }
    }

    // The stage opens the lightbox; the affordance (cursor, hint) only
    // exists once this script runs, so nothing dead ships without JS.
    var stage = mainImage.closest('.product-detail-stage');
    stage.classList.add('is-zoomable');
    stage.setAttribute('role', 'button');
    stage.setAttribute('tabindex', '0');
    stage.setAttribute('aria-label', 'Voir les photos en grand');

    function openFromStage() {
        var index = slides.findIndex(function (slide) {
            return slide.full === mainImage.src;
        });

        openBox(index === -1 ? 0 : index);
    }

    stage.addEventListener('click', openFromStage);
    stage.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openFromStage();
        }
    });

    box.addEventListener('click', function (event) {
        if (event.target === box || event.target.classList.contains('lightbox-stage')) {
            closeBox();
        }
    });
    box.querySelector('.lightbox-close').addEventListener('click', closeBox);

    var prev = box.querySelector('.lightbox-prev');
    var next = box.querySelector('.lightbox-next');

    if (prev) {
        prev.addEventListener('click', function () { show(current - 1); });
        next.addEventListener('click', function () { show(current + 1); });
    }

    boxThumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            show(parseInt(thumb.dataset.index, 10));
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!open) {
            return;
        }

        if (event.key === 'Escape') {
            closeBox();
        } else if (event.key === 'ArrowLeft' && slides.length > 1) {
            show(current - 1);
        } else if (event.key === 'ArrowRight' && slides.length > 1) {
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
        if (touchX === null || slides.length < 2) {
            return;
        }

        var dx = event.changedTouches[0].clientX - touchX;
        var dy = event.changedTouches[0].clientY - touchY;
        touchX = touchY = null;

        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
            show(dx > 0 ? current - 1 : current + 1);
        }
    }, { passive: true });
})();
