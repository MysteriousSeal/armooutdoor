(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-gmc-toggle')) {
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
                var on = data.google_feed;

                button.classList.toggle('is-set', on);
                button.classList.toggle('is-missing', !on);
                button.title = on
                    ? 'In the Google feed — click to exclude'
                    : 'Excluded from the Google feed — click to include';

                var label = button.querySelector('.sr-only');

                if (label) {
                    label.textContent = on ? 'In the Google feed' : 'Excluded from the Google feed';
                }

                if (window.armoToast) {
                    window.armoToast.show(on ? 'Category back in the Google feed.' : 'Category kept out of the Google feed.');
                }
            })
            .catch(function () {
                // Anything unexpected falls back to the ordinary page
                // submit, so the refusal is shown in full. form.submit()
                // does not re-fire this listener.
                form.submit();
            })
            .finally(function () {
                button.disabled = false;
            });
    });
})();
