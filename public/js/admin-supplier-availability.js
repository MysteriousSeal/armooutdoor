(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-supplier-availability')) {
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
                var on = data.available_at_supplier;

                button.classList.toggle('is-on', on);
                button.classList.toggle('is-off', !on);
                button.textContent = on ? 'Yes' : 'No';
                button.title = 'Click to ' + (on ? 'disable' : 'enable') + ' supplier availability';

                // The flag can flip the row's availability reading too
                // (at supplier <-> out of stock), so redraw that chip from
                // the server's own verdict rather than guessing it here.
                var availabilityChip = form.closest('tr')?.querySelector('.admin-availability-chip');

                if (availabilityChip && data.availability) {
                    availabilityChip.className = 'admin-availability-chip is-' + data.availability.replace(/_/g, '-');
                    availabilityChip.textContent = data.availability_label;
                }

                if (window.armoToast) {
                    window.armoToast.show(on ? 'Available at supplier.' : 'No longer available at supplier.');
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
