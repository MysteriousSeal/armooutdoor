(function () {
    var el = document.getElementById('analytics-config');

    if (!el) {
        return;
    }

    var config = JSON.parse(el.textContent);

    // The banner writes this cookie. Anything other than a positive answer —
    // a refusal, or no answer yet — means PostHog is never fetched at all,
    // which is the only reading of consent that holds up: a script already
    // running cannot be un-run by a later click on "Refuser".
    function accepted() {
        return /(?:^|; )cookie_consent=all(?:;|$)/.test(document.cookie);
    }

    function load() {
        if (window.posthog || !accepted()) {
            return;
        }

        var script = document.createElement('script');
        script.src = config.host + '/static/array.js';
        script.async = true;
        script.onload = function () {
            if (!window.posthog) {
                return;
            }

            window.posthog.init(config.key, {
                api_host: config.host,
                // Every click and keystroke would sweep up addresses and card
                // forms along with the rest. The shop names its own events.
                autocapture: false,
                capture_pageview: true,
                // The visitor said yes here; the library asking again in its
                // own terms would only be able to contradict that.
                persistence: 'localStorage+cookie',
                mask_all_text: true,
                disable_session_recording: true,
            });
        };

        document.head.appendChild(script);
    }

    // A visitor who accepts should be counted from that click, not from
    // whatever page they happen to load next.
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-cookie-choice="all"]')) {
            window.setTimeout(load, 0);
        }
    });

    load();

    // The shop's own events. Each is a plain name and a few figures — never a
    // name, an address or anything else a customer typed.
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!window.posthog) {
            return;
        }

        if (form.matches('.add-to-cart-form')) {
            window.posthog.capture('cart_item_added', {
                product_id: Number(form.querySelector('[name="product_id"]')?.value) || null,
                quantity: Number(form.querySelector('[name="quantity"]')?.value) || 1,
            });
        }
    });

    if (config.event) {
        var fire = function () {
            if (window.posthog) {
                window.posthog.capture(config.event.name, config.event.properties || {});
            }
        };

        // The page may load before consent is given; the event waits for the
        // library rather than being dropped.
        if (window.posthog) {
            fire();
        } else {
            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-cookie-choice="all"]')) {
                    window.setTimeout(fire, 400);
                }
            });
        }
    }
})();
