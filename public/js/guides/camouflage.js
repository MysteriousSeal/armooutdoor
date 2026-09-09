/*
 * The camouflage guide's selector: a terrain, a season, and the seven
 * families ranked against them. Revealed by this script rather than shipped
 * open, so a page without JavaScript shows every family with its terrains and
 * its seasons written out and promises no control it cannot honour.
 */
(function () {
    var root = document.querySelector('[data-cam-picker]');

    if (!root) {
        return;
    }

    var terrainField = root.querySelector('[data-cam-terrain]');
    var seasonField = root.querySelector('[data-cam-season]');
    var verdictEl = root.querySelector('[data-cam-verdict]');
    var resultsEl = root.querySelector('[data-cam-results]');
    var families = Array.prototype.slice.call(document.querySelectorAll('[data-cam-family]'));

    if (!terrainField || !seasonField || !verdictEl || !resultsEl || families.length === 0) {
        return;
    }

    // Snow is the honest exception: none of the seven is a winter pattern, and
    // the page says so rather than ranking seven wrong answers.
    var SNOW = 'neige';

    /** The pressed pill of a group. */
    function chosen(group) {
        return group.querySelector('[aria-pressed="true"]') || group.querySelector('.cam-option');
    }

    function valueOf(group) {
        return chosen(group).getAttribute('value');
    }

    /** The turn of phrase the markup carries, not a label glued to a word. */
    function phraseOf(group) {
        return chosen(group).getAttribute('data-phrase') || '';
    }

    /** One pressed at a time, and the group says which for a screen reader. */
    function press(group, button) {
        Array.prototype.forEach.call(group.querySelectorAll('.cam-option'), function (option) {
            option.setAttribute('aria-pressed', option === button ? 'true' : 'false');
        });
    }

    function capitalise(sentence) {
        return sentence.charAt(0).toUpperCase() + sentence.slice(1);
    }

    function listOf(el, name) {
        var raw = el.getAttribute(name) || '';

        return raw.split(' ').filter(function (value) {
            return value !== '';
        });
    }

    /** Two matches, one, or none: the ranking has three steps and no more. */
    function score(family, terrain, season) {
        var onTerrain = listOf(family, 'data-terrains').indexOf(terrain) !== -1;
        var inSeason = listOf(family, 'data-seasons').indexOf(season) !== -1;

        if (onTerrain && inSeason) {
            return { rank: 2, verdict: 'Tient', why: 'Le motif et la saison vont avec ce terrain.' };
        }

        if (onTerrain) {
            return { rank: 1, verdict: 'Passe', why: 'Bon terrain, mais la saison ne joue pas pour lui.' };
        }

        if (inSeason) {
            return { rank: 1, verdict: 'Passe', why: 'Bonne saison, mais il n’est pas fait pour ce terrain.' };
        }

        return { rank: 0, verdict: 'Peu adapté', why: 'Ni le terrain ni la saison ne sont les siens.' };
    }

    function row(name, result) {
        var li = document.createElement('li');
        li.className = 'cam-result' + (result.rank === 2 ? ' is-good' : '') + (result.rank === 0 ? ' is-poor' : '');

        var copy = document.createElement('span');
        copy.className = 'cam-result-name';
        copy.textContent = name;

        var why = document.createElement('span');
        why.className = 'cam-result-why';
        why.textContent = result.why;
        copy.appendChild(why);

        var verdict = document.createElement('span');
        verdict.className = 'cam-result-verdict';
        verdict.textContent = result.verdict;

        li.appendChild(copy);
        li.appendChild(verdict);

        return li;
    }

    function render() {
        var terrain = valueOf(terrainField);
        var season = valueOf(seasonField);

        // replaceChildren rather than innerHTML: nothing here is markup, and
        // the shop keeps user-shaped strings out of the parser on principle.
        resultsEl.replaceChildren();

        if (terrain === SNOW) {
            verdictEl.replaceChildren();
            verdictEl.appendChild(document.createTextNode(capitalise(phraseOf(terrainField)) + ', '));

            var strong = document.createElement('strong');
            strong.textContent = 'aucune de ces sept familles ne vaut';
            verdictEl.appendChild(strong);
            verdictEl.appendChild(document.createTextNode(
                '. Un sur-vêtement blanc par-dessus la tenue habituelle fait le travail, '
                + 'et ne sert que quelques jours par an sous nos latitudes.'
            ));

            families.forEach(function (family) {
                family.classList.remove('is-picked');
                family.classList.add('is-dimmed');
            });

            return;
        }

        var scored = families.map(function (family) {
            return { el: family, name: family.getAttribute('data-name'), result: score(family, terrain, season) };
        });

        scored.sort(function (a, b) {
            return b.result.rank - a.result.rank;
        });

        scored.forEach(function (entry) {
            resultsEl.appendChild(row(entry.name, entry.result));
            entry.el.classList.toggle('is-picked', entry.result.rank === 2);
            entry.el.classList.toggle('is-dimmed', entry.result.rank === 0);
        });

        var kept = scored.filter(function (entry) {
            return entry.result.rank === 2;
        });

        verdictEl.replaceChildren();

        var setting = capitalise(phraseOf(terrainField)) + ' ' + phraseOf(seasonField) + ', ';

        if (kept.length === 0) {
            verdictEl.appendChild(document.createTextNode(
                setting + 'aucune famille ne coche les deux cases. Prenez celle qui tient le '
                + 'terrain et cassez la silhouette avec le relief.'
            ));

            return;
        }

        var names = kept.map(function (entry) {
            return entry.name;
        });

        verdictEl.appendChild(document.createTextNode(setting));

        var picked = document.createElement('strong');
        // "CE, Woodland tiennent" is a list; "CE et Woodland tiennent" is a
        // sentence, and the verdict is written as one.
        picked.textContent = names.length > 1
            ? names.slice(0, -1).join(', ') + ' et ' + names[names.length - 1]
            : names[0];
        verdictEl.appendChild(picked);

        verdictEl.appendChild(document.createTextNode(
            names.length > 1 ? ' tiennent le mieux.' : ' tient le mieux.'
        ));
    }

    [terrainField, seasonField].forEach(function (group) {
        group.addEventListener('click', function (event) {
            var button = event.target.closest('.cam-option');

            if (!button || button.getAttribute('aria-pressed') === 'true') {
                return;
            }

            press(group, button);
            render();
        });
    });

    root.hidden = false;
    render();
})();
