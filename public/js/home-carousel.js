(function () {
    var root = document.querySelector('[data-carousel]');

    if (!root) {
        return;
    }

    var track = root.querySelector('[data-carousel-track]');
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-panel]'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-dot]'));
    var prev = root.querySelector('[data-carousel-prev]');
    var next = root.querySelector('[data-carousel-next]');

    // Un seul panneau n'est pas un carrousel : les commandes resteraient
    // cachées et le minuteur tournerait pour rien.
    if (panels.length < 2) {
        return;
    }

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var interval = parseInt(root.getAttribute('data-carousel-interval'), 10) || 6000;
    var current = 0;
    var timer = null;

    // Les commandes n'existent que si le script tourne : sans lui elles ne
    // feraient rien, et cacher un bouton mort vaut mieux que l'offrir.
    [prev, next].forEach(function (button) {
        if (button) {
            button.hidden = false;
        }
    });

    var dotsBox = root.querySelector('[data-carousel-dots]');

    if (dotsBox) {
        dotsBox.hidden = false;
    }

    function show(index) {
        current = (index + panels.length) % panels.length;

        track.style.setProperty('--carousel-offset', String(current));

        panels.forEach(function (panel, i) {
            if (i === current) {
                panel.removeAttribute('aria-hidden');
                panel.removeAttribute('inert');
            } else {
                panel.setAttribute('aria-hidden', 'true');
                // Sans inert, un lien hors écran reste atteignable au clavier
                // et le focus emmène la page sur un panneau invisible.
                panel.setAttribute('inert', '');
            }
        });

        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-current', i === current);
            dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
        });
    }

    function stop() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        stop();

        if (reduced.matches) {
            return;
        }

        timer = window.setInterval(function () {
            show(current + 1);
        }, interval);
    }

    // Une main sur le panneau ou un focus dedans veut dire qu'on le lit :
    // le faire glisser sous le lecteur serait le contraire du service rendu.
    function pauseWhile(node) {
        node.addEventListener('mouseenter', stop);
        node.addEventListener('mouseleave', start);
        node.addEventListener('focusin', stop);
        node.addEventListener('focusout', start);
    }

    function go(index) {
        show(index);
        start();
    }

    if (prev) {
        prev.addEventListener('click', function () {
            go(current - 1);
        });
    }

    if (next) {
        next.addEventListener('click', function () {
            go(current + 1);
        });
    }

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            go(parseInt(dot.getAttribute('data-carousel-dot'), 10) || 0);
        });
    });

    root.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            go(current - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            go(current + 1);
        }
    });

    pauseWhile(root);

    // Un onglet en arrière-plan ne doit pas revenir trois panneaux plus loin.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    if (typeof reduced.addEventListener === 'function') {
        reduced.addEventListener('change', start);
    }

    show(0);
    start();
})();
