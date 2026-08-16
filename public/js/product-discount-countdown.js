(function () {
    var el = document.getElementById('discount-countdown');
    var timerEl = document.getElementById('discount-countdown-timer');

    if (!el || !timerEl) {
        return;
    }

    var intervalId = null;

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function unit(value, label) {
        return '<span class="discount-countdown-unit">'
            + '<span class="discount-countdown-value">' + pad(value) + '</span>'
            + '<span class="discount-countdown-unit-label">' + label + '</span>'
            + '</span>';
    }

    function render() {
        var endsAt = el.dataset.endsAt;

        if (!endsAt) {
            el.hidden = true;
            return;
        }

        var remaining = new Date(endsAt).getTime() - Date.now();

        if (remaining <= 0) {
            timerEl.innerHTML = '';
            el.hidden = true;

            if (intervalId) {
                clearInterval(intervalId);
                intervalId = null;
            }

            return;
        }

        el.hidden = false;

        var totalSeconds = Math.floor(remaining / 1000);
        var days = Math.floor(totalSeconds / 86400);
        var hours = Math.floor((totalSeconds % 86400) / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        var html = '';

        if (days > 0) {
            html += unit(days, el.dataset.labelDays || 'j');
        }

        html += unit(hours, el.dataset.labelHours || 'h');
        html += unit(minutes, el.dataset.labelMinutes || 'min');
        html += unit(seconds, el.dataset.labelSeconds || 's');

        timerEl.innerHTML = html;
    }

    function start() {
        render();

        if (!intervalId) {
            intervalId = setInterval(render, 1000);
        }
    }

    window.ProductDiscountCountdown = {
        refresh: start,
    };

    start();
})();
