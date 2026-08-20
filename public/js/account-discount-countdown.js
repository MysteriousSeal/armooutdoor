(function () {
    var chips = Array.prototype.slice.call(document.querySelectorAll('[data-countdown-to]'));

    if (chips.length === 0) {
        return;
    }

    // Past this many days the countdown stops showing seconds, matching
    // DiscountCode::COUNTDOWN_SECONDS_WITHIN_DAYS.
    var SECONDS_WITHIN_DAYS = 7;

    // One timer for the whole page rather than one per voucher.
    var timer = null;

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    // Mirrors DiscountCode::countdownLabel(), so the first tick does not
    // rewrite what the server already rendered.
    function parts(seconds) {
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var out = [];

        if (days > 0) {
            out.push(days + 'j');
        }

        if (days > 0 || hours > 0) {
            out.push(pad(hours) + 'h');
        }

        out.push(pad(minutes) + 'm');

        if (days < SECONDS_WITHIN_DAYS) {
            out.push(pad(seconds % 60) + 's');
        }

        return out.join(' ');
    }

    // The countdown has run out, so the voucher stops offering a code that
    // checkout would now refuse.
    function markSpent(chip) {
        var voucher = chip.closest('.voucher');

        if (!voucher || voucher.classList.contains('is-spent')) {
            return;
        }

        voucher.classList.add('is-spent');

        var copy = voucher.querySelector('[data-copy-code]');

        if (copy) {
            copy.disabled = true;
        }
    }

    function tick() {
        var now = Date.now();
        var live = 0;

        chips.forEach(function (chip) {
            var text = chip.querySelector('.voucher-countdown-text');

            if (!text) {
                return;
            }

            var endsAt = Date.parse(chip.getAttribute('data-countdown-to'));

            if (isNaN(endsAt)) {
                return;
            }

            // Rounded up to match the label the server rendered.
            var seconds = Math.ceil((endsAt - now) / 1000);

            if (seconds <= 0) {
                text.textContent = chip.dataset.countdownExpired || 'Expiré';
                chip.classList.add('is-urgent');
                markSpent(chip);

                return;
            }

            live += 1;

            var template = chip.dataset.countdownTemplate || ':time';
            text.textContent = template.replace(':time', parts(seconds));

            var urgentHours = parseInt(chip.dataset.countdownUrgentHours, 10) || 48;
            chip.classList.toggle('is-urgent', seconds <= urgentHours * 3600);
        });

        // Everything has lapsed, so stop waking the page up.
        if (live === 0) {
            stop();
        }
    }

    function stop() {
        clearInterval(timer);
        timer = null;
    }

    function start() {
        if (timer === null) {
            // Assigned before the first tick, so a page where everything has
            // already lapsed can actually clear it.
            timer = setInterval(tick, 1000);
        }

        tick();
    }

    // A hidden tab has nobody watching the seconds; resync on return rather
    // than ticking in the background.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    start();
})();
