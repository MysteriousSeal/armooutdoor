(function () {
    var weaponSelect = document.querySelector('[data-line-weapon]');
    var ammoSelect = document.querySelector('[data-line-ammunition]');

    if (!weaponSelect || !ammoSelect) {
        return;
    }

    // Narrows the ammunition dropdown to boxes of the selected weapon's
    // caliber, plus the "any" option, the same behavior as the log-a-session
    // page's lines.
    function filterAmmunition() {
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

    filterAmmunition();
    weaponSelect.addEventListener('change', filterAmmunition);
})();
