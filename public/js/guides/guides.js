/*
 * Every guide's script, in one file.
 *
 * Each of the six is an immediately invoked function that looks for the one
 * element it drives and returns when the page does not carry it, so a guide
 * loading this file runs its own block and skips the other five. That guard
 * was already there when they were six files; it is what makes one file safe.
 *
 * ES5 throughout, like the rest of public/js.
 */

/* ============================== classification ==============================
 *
 * Classer son arme: the two-answer selector
 */

/*
 * The classification guide's selector: an energy band and a question,
 * four doors into the shop's own articles on categories D, C, B and A.
 */
(function () {
    var root = document.querySelector('[data-glab-selector]');

    if (!root) {
        return;
    }

    var CATALOG = {
        airsoft: {
            title: 'Hors catégorie : sous 2 joules',
            meta: 'Airsoft · pas une arme',
            body: "Les répliques du commerce français sont conçues pour rester sous 2 J. Ce n'est juridiquement pas une arme. La vente aux mineurs est interdite dès 0,08 J.",
            href: '/blog/airsoft-en-france-ce-que-dit-la-loi-avant-dacheter',
            cta: "Lire l'article",
        },
        d: {
            title: 'Catégorie D : 2 à 20 joules',
            meta: 'Majeur · pièce d\'identité',
            body: "Carabines et pistolets à plombs, billes acier, paintball : l'achat est libre pour un majeur. Le port et le transport, eux, exigent un motif légitime.",
            href: '/blog/categorie-d-ce-que-la-loi-francaise-range-vraiment-dedans-et-ce-que-ca-change-pour-vous',
            cta: 'Lire la catégorie D',
        },
        c: {
            title: 'Catégorie C : dès 20 joules',
            meta: 'Licence ou permis · compte SIA',
            body: "À 20 J exactement on change de monde : déclaration, titre de chasse ou de tir, rangement encadré. Une étiquette « 20 joules » n'est plus de la vente libre.",
            href: '/blog/categorie-c-les-armes-soumises-a-declaration-et-tout-ce-qui-va-avec',
            cta: 'Lire la catégorie C',
        },
        b: {
            title: 'Catégorie B : autorisation préalable',
            meta: 'Titre unique · 6 puis 15',
            body: "Pistolets, revolvers, certaines carabines semi-automatiques : on demande d'abord. Un titre collé à la personne, cinq ans, six armes la première fois. Trois mois de silence valent refus.",
            href: '/blog/categorie-b-les-armes-soumises-a-autorisation-et-comment-on-y-entre',
            cta: 'Lire la catégorie B',
        },
        a: {
            title: 'Catégorie A : interdit, sauf exception',
            meta: 'Chargeur · matériels de guerre',
            body: "Une arme B devient A le temps d'un chargeur inséré. Le chargeur lui-même peut déjà être classé. Hors stand et titre, c'est cinq ans et 75 000 euros.",
            href: '/blog/categorie-a-ce-qui-est-interdit-a-qui-et-comment-une-arme-b-y-bascule-dun-chargeur',
            cta: 'Lire la catégorie A',
        },
        replicas: {
            title: 'Le rayon répliques',
            meta: 'Pistolet, longue, sniper',
            body: 'Tout ce que la boutique vend en airsoft se situe sous le seuil des 2 joules, donc hors catégorie.',
            href: '/categories/repliques-airsoft',
            cta: 'Voir les répliques',
        },
    };

    var RULES = {
        'as:acheter': ['airsoft', 'replicas', 'd'],
        'as:transporter': ['airsoft', 'd', 'replicas'],
        'as:risque': ['airsoft', 'd', 'a'],
        'd:acheter': ['d', 'c', 'airsoft'],
        'd:transporter': ['d', 'c', 'airsoft'],
        'd:risque': ['d', 'c', 'a'],
        'c:acheter': ['c', 'b', 'd'],
        'c:transporter': ['c', 'd', 'b'],
        'c:risque': ['c', 'b', 'a'],
        'b:acheter': ['b', 'a', 'c'],
        'b:transporter': ['b', 'a', 'c'],
        'b:risque': ['b', 'a', 'c'],
    };

    var ARME_LABELS = {
        as: 'un airsoft sous 2 J',
        d: 'une arme de 2 à 20 J',
        c: 'une arme de 20 J et plus',
        b: 'un pistolet, un semi-auto ou un chargeur',
    };

    var BUT_LABELS = {
        acheter: "à l'achat",
        transporter: 'au transport',
        risque: 'sur les peines',
    };

    var state = { arme: 'd', but: 'acheter' };
    var results = root.querySelector('[data-glab-results]');
    var resume = root.querySelector('[data-glab-resume]');

    function render() {
        var keys = RULES[state.arme + ':' + state.but] || [];

        resume.textContent = 'Pour ' + ARME_LABELS[state.arme] + ', '
            + BUT_LABELS[state.but] + ' :';
        results.textContent = '';

        keys.forEach(function (key, index) {
            var entry = CATALOG[key];
            var item = document.createElement('li');

            var rank = document.createElement('span');
            rank.className = 'glab-reco-rank';
            rank.textContent = String(index + 1).padStart(2, '0');

            var head = document.createElement('div');
            head.className = 'glab-reco-head';

            var title = document.createElement('h3');
            title.textContent = entry.title;

            var meta = document.createElement('span');
            meta.className = 'glab-reco-meta';
            meta.textContent = entry.meta;

            head.appendChild(title);
            head.appendChild(meta);

            var body = document.createElement('p');
            body.textContent = entry.body;

            var link = document.createElement('a');
            link.href = entry.href;
            link.textContent = entry.cta;

            item.appendChild(rank);
            item.appendChild(head);
            item.appendChild(body);
            item.appendChild(link);
            results.appendChild(item);
        });
    }

    root.querySelectorAll('[data-glab-group]').forEach(function (group) {
        var name = group.getAttribute('data-glab-group');

        group.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-glab-value]');

            if (!button) {
                return;
            }

            state[name] = button.getAttribute('data-glab-value');
            group.querySelectorAll('button').forEach(function (other) {
                other.classList.toggle('is-active', other === button);
            });
            render();
        });
    });

    render();
})();

/* ============================== joules ==============================
 *
 * Joules et FPS: the calculator
 */

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

/* ============================== cibles ==============================
 *
 * Bien choisir sa cible: the two-answer selector
 */

/*
 * The lab page's 1c selector: two answers, a ranked recommendation.
 * The catalogue entries say only what the shop actually sells - one
 * basculating metal target, boards up to 42 stickers, carton in lots
 * of 20 - and every card links a real category.
 */
(function () {
    var root = document.querySelector('[data-glab-selector]');

    if (!root) {
        return;
    }

    var CATALOG = {
        reactive: {
            title: 'Cibles réactives autocollantes',
            meta: 'Ø 76 mm · lots de 100 à 250',
            body: "Chaque impact fait éclater un anneau fluo, visible à la lunette comme à l'œil nu. Se collent sur un carton usé ou une vieille planche.",
            href: '/categories/cibles-rondes',
            cta: 'Voir les rondes',
        },
        reactive10: {
            title: 'Réactives Ø 10 cm',
            meta: 'Ø 100 mm · lots de 100',
            body: "Plus tolérantes : distances longues, calibres remuants, ou premiers tirs d'un débutant qui a besoin de voir ses réussites.",
            href: '/categories/cibles-rondes',
            cta: 'Voir les rondes',
        },
        planche: {
            title: 'Planches multi-cibles',
            meta: "Jusqu'à 42 cibles · 20 x 20 cm",
            body: "Des dizaines de pastilles neuves sur une feuille, certaines avec grille de réglage en clics : un agrafage couvre la séance entière.",
            href: '/categories/planches-cibles',
            cta: 'Voir les planches',
        },
        carton: {
            title: 'Carton, blasons et score',
            meta: 'Lots de 20 feuilles',
            body: "Huit blasons ou zones de score par feuille : on note, on archive, on compare d'une séance à l'autre.",
            href: '/categories/cibles-carton-metal',
            cta: 'Voir carton & métal',
        },
        grille: {
            title: 'Carrées à grille de zérotage',
            meta: '51 mm à 10 cm · lots de 100 à 200',
            body: "La grille donne la correction en clics, ligne par ligne, colonne par colonne. La cible du réglage, pas celle du score.",
            href: '/categories/cibles-carrees',
            cta: 'Voir les carrées',
        },
        metal: {
            title: 'Cible basculante 5 plaques',
            meta: 'Acier · 4,5 et 5,5 mm',
            body: "Quatre plaques tombent, un tir sur la cinquième les relève. Aucun consommable, réservée aux airguns 4,5 et 5,5 mm.",
            href: '/categories/cibles-carton-metal',
            cta: 'Voir carton & métal',
        },
    };

    var RULES = {
        'impact:court': ['reactive', 'planche', 'carton'],
        'impact:moyen': ['reactive', 'reactive10', 'planche'],
        'impact:long': ['reactive10', 'planche', 'carton'],
        'score:court': ['carton', 'planche', 'reactive'],
        'score:moyen': ['carton', 'planche', 'reactive'],
        'score:long': ['carton', 'reactive10', 'planche'],
        'optique:court': ['grille', 'planche', 'reactive'],
        'optique:moyen': ['grille', 'planche', 'carton'],
        'optique:long': ['grille', 'carton', 'reactive10'],
        'rythme:court': ['planche', 'reactive', 'metal'],
        'rythme:moyen': ['metal', 'planche', 'reactive10'],
        'rythme:long': ['metal', 'carton', 'reactive10'],
    };

    var RESUMES = {
        impact: 'Pour lire vos impacts',
        score: 'Pour un score comparable',
        optique: 'Pour régler une optique',
        rythme: 'Pour du tir ludique',
    };

    var DIST_LABELS = { court: 'à 10 mètres', moyen: 'à 25 mètres', long: 'à 50 mètres et plus' };

    var state = { dist: 'moyen', but: 'impact' };
    var results = root.querySelector('[data-glab-results]');
    var resume = root.querySelector('[data-glab-resume]');

    function render() {
        var keys = RULES[state.but + ':' + state.dist] || [];

        resume.textContent = RESUMES[state.but] + ' ' + DIST_LABELS[state.dist] + ' :';
        results.textContent = '';

        keys.forEach(function (key, index) {
            var entry = CATALOG[key];
            var item = document.createElement('li');

            var rank = document.createElement('span');
            rank.className = 'glab-reco-rank';
            rank.textContent = String(index + 1).padStart(2, '0');

            var head = document.createElement('div');
            head.className = 'glab-reco-head';

            var title = document.createElement('h3');
            title.textContent = entry.title;

            var meta = document.createElement('span');
            meta.className = 'glab-reco-meta';
            meta.textContent = entry.meta;

            head.appendChild(title);
            head.appendChild(meta);

            var body = document.createElement('p');
            body.textContent = entry.body;

            var link = document.createElement('a');
            link.href = entry.href;
            link.textContent = entry.cta;

            item.appendChild(rank);
            item.appendChild(head);
            item.appendChild(body);
            item.appendChild(link);
            results.appendChild(item);
        });
    }

    root.querySelectorAll('[data-glab-group]').forEach(function (group) {
        var name = group.getAttribute('data-glab-group');

        group.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-glab-value]');

            if (!button) {
                return;
            }

            state[name] = button.getAttribute('data-glab-value');
            group.querySelectorAll('button').forEach(function (other) {
                other.classList.toggle('is-active', other === button);
            });
            render();
        });
    });

    render();
})();

/* ============================== camouflage ==============================
 *
 * Choisir son camouflage: the terrain and season picker
 */

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

/* ============================== entretien ==============================
 *
 * Entretenir son arme: the two-answer selector
 */

/*
 * The entretien guide's selector: a calibre and a place, a ranked kit.
 * Every entry is something the shop actually sells, checked against the
 * production catalogue - one rope per calibre family, one 16-piece rod
 * kit covering .22 to .357, and the bench-side companions.
 */
(function () {
    var root = document.querySelector('[data-glab-selector]');

    if (!root) {
        return;
    }

    var ROPES = {
        45: { label: '.17 · 4,5 mm', slug: 'corde-nettoyage-canon-carabine-17-177-17hmr-17wmr-45mm-bore-rope', family: '.17 · .177 · 4,5 mm' },
        22: { label: '.22 LR · 5,56 mm', slug: 'corde-nettoyage-canon-22-223-5-56mm-bore-rope', family: '.22 · .223 · 5,56 mm' },
        25: { label: '.25 · 6,35 mm', slug: 'corde-nettoyage-canon-carabine-25-264-635mm-bore-rope', family: '.25 · .264 · 6,35 mm' },
        9: { label: '9 mm · .38 · .357', slug: 'corde-nettoyage-canon-38-357-380-9mm-bore-rope', family: '.38 · .357 · .380 · 9 mm' },
        308: { label: '.308 · 7,62 mm', slug: 'corde-nettoyage-canon-carabine-30-308-30-06-300-303-7-62mm-bore-rope', family: '.30 · .308 · 7,62 mm' },
        12: { label: 'calibre 12', slug: 'corde-nettoyage-canon-calibre-12-bore-rope', family: 'Calibre 12' },
    };

    // The rod kit only reaches these bores; other calibres get the bench
    // companions instead of a kit that would not fit their barrel.
    var KIT_COVERS = ['22', '9'];

    var EXTRAS = {
        kit: {
            title: 'Kit universel 16 pièces',
            meta: 'Tiges laiton · .22, 9 mm, .40, .357',
            body: "Tiges, brosses et écouvillons pour le nettoyage complet à l'établi, chambre comprise.",
            href: '/products/kit-de-nettoyage-universel-pour-armes-16-pieces-tiges-en-laiton-calibres-22-9mm-40-et-357',
            cta: 'Voir le kit',
        },
        etiquette: {
            title: 'Étiquettes chambre vide',
            meta: 'Lot de 2 · universelles',
            body: "Le drapeau qui rend l'arme visiblement sûre, avant l'entretien comme sur la ligne.",
            href: '/products/lot-2-etiquettes-chambre-vide-brodees-rouges-porte-cles-securite-fusil-pistolet-universel',
            cta: 'Voir les étiquettes',
        },
        recuperateur: {
            title: 'Récupérateur de douilles',
            meta: 'Filet rail ou sac',
            body: 'La ligne reste propre et le laiton rentre à la maison au lieu de finir au sol.',
            href: '/categories/recuperateurs-de-douilles',
            cta: 'Voir les récupérateurs',
        },
        tapis: {
            title: 'Tapis de tir',
            meta: 'Étanche · pliable',
            body: "Protège l'établi et la crosse pendant le démontage, et sert de poste au stand.",
            href: '/categories/kit-stand-tir',
            cta: 'Voir le kit stand',
        },
    };

    var state = { cal: '22', lieu: 'stand' };
    var results = root.querySelector('[data-glab-results]');
    var resume = root.querySelector('[data-glab-resume]');

    function ropeCard(cal) {
        var rope = ROPES[cal];

        return {
            title: 'Corde de nettoyage ' + rope.family,
            meta: 'Bore rope · lavable',
            body: 'Brosse laiton et tissu en un seul passage, de la chambre vers la bouche. Tient dans une poche de sac de stand.',
            href: '/products/' + rope.slug,
            cta: 'Voir la corde',
        };
    }

    function cards() {
        if (state.lieu === 'stand') {
            return [ropeCard(state.cal), EXTRAS.etiquette, EXTRAS.recuperateur];
        }

        return KIT_COVERS.indexOf(state.cal) !== -1
            ? [ropeCard(state.cal), EXTRAS.kit, EXTRAS.tapis]
            : [ropeCard(state.cal), EXTRAS.tapis, EXTRAS.etiquette];
    }

    function render() {
        resume.textContent = 'Pour votre ' + ROPES[state.cal].label + ', '
            + (state.lieu === 'stand' ? 'au stand' : "à l'établi") + ' :';
        results.textContent = '';

        cards().forEach(function (entry, index) {
            var item = document.createElement('li');

            var rank = document.createElement('span');
            rank.className = 'glab-reco-rank';
            rank.textContent = String(index + 1).padStart(2, '0');

            var head = document.createElement('div');
            head.className = 'glab-reco-head';

            var title = document.createElement('h3');
            title.textContent = entry.title;

            var meta = document.createElement('span');
            meta.className = 'glab-reco-meta';
            meta.textContent = entry.meta;

            head.appendChild(title);
            head.appendChild(meta);

            var body = document.createElement('p');
            body.textContent = entry.body;

            var link = document.createElement('a');
            link.href = entry.href;
            link.textContent = entry.cta;

            item.appendChild(rank);
            item.appendChild(head);
            item.appendChild(body);
            item.appendChild(link);
            results.appendChild(item);
        });
    }

    root.querySelectorAll('[data-glab-group]').forEach(function (group) {
        var name = group.getAttribute('data-glab-group');

        group.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-glab-value]');

            if (!button) {
                return;
            }

            state[name] = button.getAttribute('data-glab-value');
            group.querySelectorAll('button').forEach(function (other) {
                other.classList.toggle('is-active', other === button);
            });
            render();
        });
    });

    render();
})();

/* ============================== glossaire ==============================
 *
 * Le glossaire: the filter and the tally
 */

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
