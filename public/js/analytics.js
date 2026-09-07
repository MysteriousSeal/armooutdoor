(function () {
    var el = document.getElementById('analytics-config');

    if (!el) {
        return;
    }

    var config = JSON.parse(el.textContent);

    // The banner writes this cookie. Anything other than a positive answer,
    // a refusal or no answer yet, leaves PostHog unfetched and Google's tag
    // running with every storage signal denied. Nothing is loaded first and
    // asked afterwards: a script already running cannot be un-run by a
    // later click on "Refuser".
    function accepted() {
        return /(?:^|; )cookie_consent=all(?:;|$)/.test(document.cookie);
    }

    // Consent Mode v2. The four signals Google reads before it decides what
    // it may keep on the device, which is a different question from whether
    // its tag runs at all.
    function consent(granted) {
        return {
            analytics_storage: granted ? 'granted' : 'denied',
            ad_storage: granted ? 'granted' : 'denied',
            ad_user_data: granted ? 'granted' : 'denied',
            // Denied on either answer. The shop measures its own
            // advertising; it does not build audiences out of the people
            // who walk past.
            ad_personalization: 'denied',
        };
    }

    function loadGoogle() {
        if (window.gtag || (!config.ga && !config.aw)) {
            return;
        }

        var granted = accepted();

        window.dataLayer = window.dataLayer || [];
        window.gtag = function () {
            window.dataLayer.push(arguments);
        };

        // Both of these are queued before the tag is requested. A default
        // that arrives once the tag has started is a default that arrived
        // too late, and the tag would have assumed it could store.
        window.gtag('consent', 'default', consent(granted));
        // Without storage consent the ad click id is stripped out of the
        // request rather than carried in it.
        window.gtag('set', 'ads_data_redaction', !granted);

        // One loader serves both properties; either id fetches the same tag.
        var tag = document.createElement('script');
        tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.ga || config.aw);
        tag.async = true;
        document.head.appendChild(tag);

        window.gtag('js', new Date());

        if (config.ga) {
            window.gtag('config', config.ga, {
                // Held alongside the consent signals rather than instead of
                // them: these two stay off whatever the visitor answered.
                allow_google_signals: false,
                allow_ad_personalization_signals: false,
            });
        }

        if (config.aw) {
            // The Ads side: conversion measurement, which the consent
            // signals above govern rather than this call.
            window.gtag('config', config.aw);
        }
    }

    // Captures made before PostHog has finished loading, replayed in order
    // once it has. Google queues its own through the dataLayer, which is why
    // its side never needed this.
    var pending = [];

    // One call site, two vocabularies. PostHog takes the shop's own names and
    // figures; Google takes its reserved ones, without which a sale is a
    // counter that ticks and never reaches a revenue report.
    function capture(name, properties, google) {
        if (window.posthog) {
            window.posthog.capture(name, properties || {});
        } else if (config.key) {
            // The library is a network request behind the page. Dropping the
            // event here is what lost the purchase on every confirmation page
            // a consenting visitor loaded fresh. Nothing leaves the browser
            // until PostHog is loaded, which consent alone decides.
            pending.push([name, properties || {}]);
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

            // Whatever happened while the library was in flight, in the
            // order it happened.
            while (pending.length) {
                var queued = pending.shift();
                window.posthog.capture(queued[0], queued[1]);
            }
        };

        document.head.appendChild(script);
    }

    // The answer, once it is given. Google's tag is already running, so
    // nothing is fetched here: the update is the only thing that changes
    // what it may keep on the device.
    function grantGoogle() {
        if (!window.gtag) {
            return;
        }

        window.gtag('consent', 'update', consent(true));
        window.gtag('set', 'ads_data_redaction', false);
    }

    function load() {
        loadPostHog();
        loadGoogle();
    }

    // A visitor who accepts should be counted from that click, not from
    // whatever page they happen to load next.
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-cookie-choice="all"]')) {
            window.setTimeout(function () {
                loadPostHog();
                grantGoogle();
            }, 0);
        }
    });

    load();

    // The shop's own events. Each is a plain name and a few figures — never a
    // name, an address or anything else a customer typed.
    document.addEventListener('submit', function (event) {
        var form = event.target;

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

    // Fired once, here. Google takes it straight away in whatever consent
    // state its tag is running; PostHog takes it off the queue whenever it
    // arrives, which may be after the visitor has answered the banner. There
    // is no second call site, so a sale cannot be counted twice.
    if (config.event) {
        capture(config.event.name, config.event.properties || {}, config.event.ga || null);

        // The Ads conversion is its own reserved event, deduplicated by
        // Google on the transaction id it carries.
        if (window.gtag && config.aw && config.event.aw) {
            window.gtag('event', 'conversion', config.event.aw);
        }
    }
})();
