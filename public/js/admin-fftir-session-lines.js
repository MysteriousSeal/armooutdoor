(function () {
    var list = document.getElementById('session-lines');
    var template = document.getElementById('session-line-row-template');
    var addButton = document.getElementById('session-add-line');

    if (!list || !template || !addButton) {
        return;
    }

    var nextIndex = list.querySelectorAll('.fftir-line').length;

    // Narrows the ammunition dropdown to boxes of the selected weapon's
    // caliber, plus the "any" option: no separate caliber field, since a
    // weapon already only takes one.
    function filterAmmunition(line) {
        var weaponSelect = line.querySelector('[data-line-weapon]');
        var ammoSelect = line.querySelector('[data-line-ammunition]');

        if (!weaponSelect || !ammoSelect) {
            return;
        }

        var weaponOption = weaponSelect.selectedOptions[0];
        var caliber = weaponOption ? weaponOption.getAttribute('data-caliber') : null;

        Array.prototype.forEach.call(ammoSelect.options, function (option) {
            if (!option.value) {
                return;
            }

            var matches = !caliber || option.getAttribute('data-caliber') === caliber;
            option.hidden = !matches;

            if (!matches && option.selected) {
                ammoSelect.value = '';
            }
        });
    }

    list.querySelectorAll('.fftir-line').forEach(filterAmmunition);

    addButton.addEventListener('click', function () {
        var html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        var holder = document.createElement('div');
        holder.innerHTML = html;
        var row = holder.firstElementChild;
        list.appendChild(row);
        filterAmmunition(row);
    });

    list.addEventListener('change', function (event) {
        if (event.target.matches('[data-line-weapon]')) {
            filterAmmunition(event.target.closest('.fftir-line'));
        }
    });

    list.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-remove-line]');

        if (!remove) {
            return;
        }

        // A session without a line makes no sense, and the form would
        // reject it anyway.
        if (list.querySelectorAll('.fftir-line').length > 1) {
            remove.closest('.fftir-line').remove();
        }
    });
})();
