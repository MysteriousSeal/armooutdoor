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

    // Le libellé vient d'un data-attribut, donc d'une chaîne de traduction :
    // collé dans du HTML, un chevron oublié dans une traduction deviendrait
    // une balise. Posé au textContent, il ne peut être que du texte.
    function unit(value, label) {
        var wrap = document.createElement('span');
        wrap.className = 'discount-countdown-unit';

        var figure = document.createElement('span');
        figure.className = 'discount-countdown-value';
        figure.textContent = pad(value);
        wrap.appendChild(figure);

        var caption = document.createElement('span');
        caption.className = 'discount-countdown-unit-label';
        caption.textContent = label;
        wrap.appendChild(caption);

        return wrap;
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
        var parts = document.createDocumentFragment();

        if (days > 0) {
            parts.appendChild(unit(days, el.dataset.labelDays || 'j'));
        }

        parts.appendChild(unit(hours, el.dataset.labelHours || 'h'));
        parts.appendChild(unit(minutes, el.dataset.labelMinutes || 'min'));
        parts.appendChild(unit(seconds, el.dataset.labelSeconds || 's'));

        while (timerEl.firstChild) {
            timerEl.removeChild(timerEl.firstChild);
        }

        timerEl.appendChild(parts);
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
