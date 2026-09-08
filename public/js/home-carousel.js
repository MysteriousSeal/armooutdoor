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

    // Touch has neither the arrows (hidden once the panels stack) nor a
    // real target in the dots: without a swipe, the next panel is only
    // reachable by waiting for the timer to bring it round.
    (function enableSwipe() {
        var viewport = root.querySelector('.home-carousel-viewport');

        if (!viewport || typeof viewport.setPointerCapture !== 'function') {
            return;
        }

        var startX = 0;
        var startY = 0;
        var delta = 0;
        var pointer = null;
        var axis = null;
        var swallowClick = false;

        function reset() {
            pointer = null;
            axis = null;
            delta = 0;
            track.classList.remove('is-dragging');
            track.style.removeProperty('--carousel-drag');
        }

        viewport.addEventListener('pointerdown', function (event) {
            // A mouse has the arrows and the dots; taking its drag would
            // only cost the reader the ability to select the text.
            if (pointer !== null || event.button !== 0 || event.pointerType === 'mouse') {
                return;
            }

            pointer = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;
            delta = 0;
            axis = null;
        });

        viewport.addEventListener('pointermove', function (event) {
            if (pointer !== event.pointerId) {
                return;
            }

            var dx = event.clientX - startX;
            var dy = event.clientY - startY;

            // Until the direction is settled the page keeps the gesture: a
            // finger going down must scroll, not drag.
            if (axis === null) {
                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) {
                    return;
                }

                if (Math.abs(dy) >= Math.abs(dx)) {
                    pointer = null;
                    return;
                }

                axis = 'x';
                viewport.setPointerCapture(event.pointerId);
                track.classList.add('is-dragging');
                stop();
            }

            // The track resists at either end: there is nothing to show
            // beyond it, and the pull back says so without a message.
            var edge = (current === 0 && dx > 0) || (current === panels.length - 1 && dx < 0);
            delta = edge ? dx / 3.5 : dx;
            track.style.setProperty('--carousel-drag', delta + 'px');
        });

        function release(event) {
            if (pointer !== event.pointerId) {
                return;
            }

            var moved = axis === 'x' ? delta : 0;
            // Enough of the panel to be a decision rather than a tremor.
            var threshold = Math.max(40, viewport.offsetWidth * 0.12);

            swallowClick = Math.abs(moved) > 8;
            reset();

            if (moved <= -threshold) {
                go(current + 1);
            } else if (moved >= threshold) {
                go(current - 1);
            } else {
                start();
            }
        }

        viewport.addEventListener('pointerup', release);
        viewport.addEventListener('pointercancel', release);

        // A swipe often ends on a button: without this guard, letting go on
        // one opens the page the reader was swiping away from.
        viewport.addEventListener('click', function (event) {
            if (swallowClick) {
                swallowClick = false;
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
    })();

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
