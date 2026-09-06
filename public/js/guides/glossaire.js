/*
 * The glossary's filter: type, and the entries that do not answer step
 * back. Revealed by this script rather than shipped open, because a
 * search field that filters nothing is worse than no field at all - the
 * whole list and the letter index work without it.
 */
(function () {
    var search = document.querySelector('[data-gloss-search]');
    var list = document.querySelector('[data-gloss-list]');

    if (!search || !list) {
        return;
    }

    var input = search.querySelector('[data-gloss-input]');
    var count = search.querySelector('[data-gloss-count]');
    var empty = list.querySelector('[data-gloss-empty]');
    var groups = list.querySelectorAll('[data-gloss-group]');
    var entries = list.querySelectorAll('[data-gloss-entry]');
    var rail = document.querySelector('.gloss-rail');

    // Accents fold, so « graduee » finds « graduée » and « energie »
    // finds « énergie ».
    function fold(value) {
        return value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    var haystacks = [];

    entries.forEach(function (entry) {
        haystacks.push(fold(entry.dataset.glossTerms + ' ' + entry.textContent));
    });

    function render() {
        var needle = fold(input.value.trim());
        var shown = 0;

        entries.forEach(function (entry, index) {
            var matches = needle === '' || haystacks[index].indexOf(needle) !== -1;

            entry.hidden = !matches;

            if (matches) {
                shown += 1;
            }
        });

        // A letter whose every entry has stepped back goes with them.
        groups.forEach(function (group) {
            group.hidden = group.querySelectorAll('[data-gloss-entry]:not([hidden])').length === 0;
        });

        empty.hidden = shown !== 0;

        if (rail) {
            rail.hidden = needle !== '';
        }

        if (needle === '') {
            count.textContent = '';

            return;
        }

        count.textContent = shown === 0
            ? 'Aucun mot'
            : shown + (shown > 1 ? ' mots' : ' mot');
    }

    input.addEventListener('input', render);

    // Escape clears rather than leaving the reader stuck in a filtered list.
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && input.value !== '') {
            event.preventDefault();
            input.value = '';
            render();
        }
    });

    search.hidden = false;
    render();
})();
