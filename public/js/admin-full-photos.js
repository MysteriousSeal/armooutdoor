// The Full photos mark of the product list, ticked without reloading the page,
// so a long run of products can be marked without losing the scroll position.
//
// The forms work on their own as a real PATCH; this only intercepts them.
(function () {
    'use strict';

    var CHECK = 'm5 13 4 4L19 7';
    var CROSS = 'M6 6l12 12M18 6 6 18';

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-full-photos')) {
            return;
        }

        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');

        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                var on = data.full_photos;

                button.classList.toggle('is-set', on);
                button.classList.toggle('is-missing', !on);
                button.title = on
                    ? 'Model photos and infographics uploaded: click to unmark'
                    : 'Model photos and infographics not uploaded yet: click to mark';
                button.querySelector('path').setAttribute('d', on ? CHECK : CROSS);
                button.querySelector('.sr-only').textContent = on ? 'Full photos' : 'Photos missing';

                var count = document.querySelector('[data-full-photos-count]');
                var percent = document.querySelector('[data-full-photos-percent]');

                // A variant's reply carries no stats: the header counts products.
                if (count && percent && data.full_photos_stats) {
                    count.textContent = data.full_photos_stats.count;
                    percent.textContent = data.full_photos_stats.percent;
                }

                if (window.armoToast) {
                    window.armoToast.show(data.message);
                }
            })
            .catch(function () {
                // Anything unexpected falls back to the ordinary page submit,
                // which shows the refusal in full. form.submit() does not
                // re-fire this listener.
                form.submit();
            })
            .finally(function () {
                button.disabled = false;
            });
    });
})();
