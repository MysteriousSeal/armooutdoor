(function () {
    var POLL_INTERVAL_MS = 10000;

    var script = document.currentScript;
    var chip = document.getElementById('admin-nav-active-now');

    if (!script || !chip) {
        return;
    }

    var url = script.getAttribute('data-active-now-url');
    var countEl = chip.querySelector('.admin-active-now-count');

    // People only: bots stay on the analytics page.
    function render(data) {
        countEl.textContent = data.humans;
        chip.classList.toggle('is-live', data.humans > 0);
        chip.title = data.humans
            + ' active (' + data.users + ' logged in, ' + data.guests + ' '
            + (data.guests === 1 ? 'guest' : 'guests') + ') in the last 2 minutes';
    }

    function poll() {
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (data) {
                    render(data);
                }
            })
            .catch(function () {});
    }

    poll();
    setInterval(poll, POLL_INTERVAL_MS);
})();
