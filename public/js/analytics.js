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

    function loadGoogle() {
        if (window.gtag || !config.ga || !accepted()) {
            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.gtag = function () {
            window.dataLayer.push(arguments);
        };

        var tag = document.createElement('script');
        tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.ga);
        tag.async = true;
        document.head.appendChild(tag);

        window.gtag('js', new Date());
        window.gtag('config', config.ga, {
            // The visitor already answered the question on the banner; asking
            // again in Google's own terms could only contradict them.
            anonymize_ip: true,
            allow_google_signals: false,
            allow_ad_personalization_signals: false,
        });
    }

    // One call site, two vocabularies. PostHog takes the shop's own names and
    // figures; Google takes its reserved ones, without which a sale is a
    // counter that ticks and never reaches a revenue report.
    function capture(name, properties, google) {
        if (window.posthog) {
            window.posthog.capture(name, properties || {});
        }

        if (window.gtag && google && google.name) {
            window.gtag('event', google.name, google.properties || {});
        }
    }

    function loadPostHog() {
        if (window.posthog || !config.key || !accepted()) {
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

    function load() {
        loadPostHog();
        loadGoogle();
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

        if (!window.posthog && !window.gtag) {
            return;
        }

        if (form.matches('.add-to-cart-form')) {
            var quantity = Number(form.querySelector('[name="quantity"]')?.value) || 1;
            var item = null;

            try {
                item = JSON.parse(form.getAttribute('data-analytics-item') || 'null');
            } catch (error) {
                item = null;
            }

            if (item) {
                item.quantity = quantity;
            }

            capture(
                'cart_item_added',
                {
                    product_id: Number(form.querySelector('[name="product_id"]')?.value) || null,
                    quantity: quantity,
                },
                item
                    ? {
                        name: 'add_to_cart',
                        properties: {
                            currency: 'EUR',
                            value: Math.round(item.price * quantity * 100) / 100,
                            items: [item],
                        },
                    }
                    : null
            );
        }
    });

    if (config.event) {
        var fire = function () {
            capture(config.event.name, config.event.properties || {}, config.event.ga || null);
        };

        // The page may load before consent is given; the event waits for the
        // library rather than being dropped.
        if (window.posthog || window.gtag) {
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
