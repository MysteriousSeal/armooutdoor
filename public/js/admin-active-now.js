(function () {
    var POLL_INTERVAL_MS = 10000;

    var script = document.currentScript;
    var chip = document.getElementById('admin-nav-active-now');

    if (!script || !chip) {
        return;
    }

    var url = script.getAttribute('data-active-now-url');
    var countEl = chip.querySelector('.admin-active-now-count');

    function render(data) {
        countEl.textContent = data.total;
        chip.classList.toggle('is-live', data.total > 0);
        chip.title = data.total
            + ' active (' + data.users + ' logged in, ' + data.guests + ' '
            + (data.guests === 1 ? 'guest' : 'guests') + ', ' + data.bots + ' '
            + (data.bots === 1 ? 'bot' : 'bots') + ') — last 2 minutes';
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
