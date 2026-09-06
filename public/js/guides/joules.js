/*
 * The joules guide's calculator: a bille, a chronograph reading, and the
 * energy that follows. Revealed by this script rather than shipped open,
 * so a page without JavaScript shows the reference table and promises no
 * control it cannot honour.
 */
(function () {
    var root = document.querySelector('[data-glab-joules]');

    if (!root) {
        return;
    }

    var FOOT = 0.3048;

    // The axis the needle rides, matched to guides/partials/energy-scale.
    var SCALE_MIN = 0.08;
    var SCALE_MAX = 100;

    // The three usual field caps, then the one that is actually in the code.
    var LIMITS = [
        { joules: 1.14, law: false },
        { joules: 1.49, law: false },
        { joules: 1.88, law: false },
        { joules: 2, law: true },
    ];

    var weightSelect = root.querySelector('[data-glab-weight]');
    var customField = root.querySelector('[data-glab-custom]');
    var customInput = root.querySelector('[data-glab-custom-input]');
    var speedInput = root.querySelector('[data-glab-speed]');
    var unitSelect = root.querySelector('[data-glab-unit]');
    var energyValue = root.querySelector('[data-glab-joules-value]');
    var equiv = root.querySelector('[data-glab-equiv]');
    var band = root.querySelector('[data-glab-band]');
    var limits = root.querySelector('[data-glab-limits]');
    var scale = root.querySelector('[data-glab-scale]');
    var names = root.querySelectorAll('[data-glab-name]');

    // Where an energy sits on the logarithmic axis, in per cent.
    function scalePosition(joules) {
        var span = Math.log10(SCALE_MAX) - Math.log10(SCALE_MIN);
        var at = (Math.log10(Math.max(joules, SCALE_MIN)) - Math.log10(SCALE_MIN)) / span * 100;

        return Math.max(0, Math.min(100, at));
    }

    function zoneOf(joules) {
        if (joules < 2) {
            return 'is-free';
        }

        return joules < 20 ? 'is-d' : 'is-c';
    }

    function decimal(value, places) {
        return value.toFixed(places).replace('.', ',');
    }

    function grams() {
        if (weightSelect.value === 'custom') {
            return parseFloat(customInput.value);
        }

        return parseFloat(weightSelect.value);
    }

    function metresPerSecond() {
        var speed = parseFloat(speedInput.value);

        return unitSelect.value === 'ms' ? speed : speed * FOOT;
    }

    // The band the law puts this energy in. Twenty joules exactly is
    // already category C: the text reads « supérieure ou égale ».
    function bandFor(joules) {
        if (joules < 2) {
            return 'Sous 2 J : juridiquement pas une arme';
        }

        if (joules < 20) {
            return 'De 2 à 20 J : catégorie D, vente libre pour un majeur';
        }

        return 'Dès 20 J : catégorie C, soumise à déclaration';
    }

    function render() {
        var mass = grams();
        var velocity = metresPerSecond();

        if (!isFinite(mass) || !isFinite(velocity) || mass <= 0 || velocity <= 0) {
            return;
        }

        var joules = 0.5 * (mass / 1000) * velocity * velocity;

        energyValue.textContent = decimal(joules, 2);
        equiv.textContent = Math.round(velocity / FOOT) + ' FPS · ' + Math.round(velocity) + ' m/s';
        band.textContent = bandFor(joules);

        // The needle, and the zone it has landed in, named as well as pointed at.
        var zone = zoneOf(joules);

        scale.style.setProperty('--glab-scale-at', scalePosition(joules).toFixed(2) + '%');

        names.forEach(function (name) {
            name.classList.toggle('is-current', name.dataset.glabName === zone);
        });

        // v = √(2E / m): the speed each cap allows with the bille chosen.
        limits.innerHTML = '';

        LIMITS.forEach(function (limit) {
            var ceiling = Math.sqrt((2 * limit.joules) / (mass / 1000));
            var item = document.createElement('li');
            var label = document.createElement('span');
            var value = document.createElement('strong');

            label.textContent = decimal(limit.joules, 2) + ' J';
            value.textContent = Math.round(ceiling / FOOT) + ' FPS';

            if (limit.law) {
                item.className = 'is-law';
            }

            item.append(label, value);
            limits.append(item);
        });
    }

    weightSelect.addEventListener('change', function () {
        customField.hidden = weightSelect.value !== 'custom';

        if (!customField.hidden) {
            customInput.focus();
        }

        render();
    });

    [customInput, speedInput, unitSelect].forEach(function (control) {
        control.addEventListener('input', render);
        control.addEventListener('change', render);
    });

    root.hidden = false;
    render();
})();
