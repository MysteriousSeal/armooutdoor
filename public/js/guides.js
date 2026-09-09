/*
 * Every guide's script, in one file.
 *
 * Each block is an immediately invoked function that looks for the one
 * element it drives and returns when the page does not carry it, so a guide
 * loading this file runs its own block and skips every other. That guard was
 * already there when they were separate files; it is what makes one file safe.
 *
 * ES5 throughout, like the rest of public/js.
 */

/* ============================== selector ==============================
 *
 * The two-answer selector every guide shares
 */

/*
 * Four guides ask a reader two questions and answer them: three name a
 * handful of products, and the camouflage guide ranks seven motifs. They had
 * a script each, and three of those differed only in their data and in the
 * sentence they wrote.
 *
 * What differs between them is the guide's own knowledge, so that is all a
 * guide supplies: a function turning the two answers into a sentence and a
 * list of cards. The chips, the state, the pressed class and the rendering
 * are written once here.
 *
 * The answer a guide returns is { resume, cards }, and a card is
 * { rank, title, meta, body, href, cta, tone }. Every field is optional
 * except the title, which is what lets one shape carry both a product the
 * shop sells and a verdict on a motif.
 */
var GlabSelector = (function () {
    function fill(parent, tag, className, text) {
        if (!text) {
            return null;
        }

        var el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        el.textContent = text;
        parent.appendChild(el);

        return el;
    }

    function card(entry, index) {
        var item = document.createElement('li');

        if (entry.tone) {
            item.className = 'is-' + entry.tone;
        }

        // The rank is a number where the guide ranks, and a verdict where it
        // judges: the same small uppercase label either way.
        fill(item, 'span', 'glab-reco-rank', entry.rank || String(index + 1).padStart(2, '0'));

        var head = document.createElement('div');
        head.className = 'glab-reco-head';
        fill(head, 'h3', '', entry.title);
        fill(head, 'span', 'glab-reco-meta', entry.meta);
        item.appendChild(head);

        fill(item, 'p', '', entry.body);

        if (entry.href) {
            var link = document.createElement('a');
            link.href = entry.href;
            link.textContent = entry.cta || 'Voir';
            item.appendChild(link);
        }

        return item;
    }

    /**
     * Wires one selector. `resolve` receives the reader's answers, keyed by
     * the name of each group, with the turn of phrase the markup carries
     * beside it: French grammar stays in the Blade file, where it can be
     * written as a sentence rather than glued together here.
     */
    function mount(selector, resolve, after) {
        var root = document.querySelector(selector);

        if (!root) {
            return;
        }

        var resume = root.querySelector('[data-glab-resume]');
        var results = root.querySelector('[data-glab-results]');
        var groups = Array.prototype.slice.call(root.querySelectorAll('[data-glab-group]'));

        if (!resume || !results || groups.length === 0) {
            return;
        }

        var state = {};

        function read(group) {
            var name = group.getAttribute('data-glab-group');
            var button = group.querySelector('button.is-active') || group.querySelector('button');

            state[name] = button.getAttribute('data-glab-value');
            state[name + 'Phrase'] = button.getAttribute('data-glab-phrase') || '';
        }

        function render() {
            var answer = resolve(state) || {};

            resume.replaceChildren();
            resume.appendChild(document.createTextNode(answer.resume || ''));
            results.replaceChildren();

            (answer.cards || []).forEach(function (entry, index) {
                results.appendChild(card(entry, index));
            });

            if (after) {
                after(state, answer);
            }
        }

        groups.forEach(function (group) {
            read(group);

            group.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-glab-value]');

                if (!button) {
                    return;
                }

                group.querySelectorAll('button').forEach(function (other) {
                    other.classList.toggle('is-active', other === button);
                });

                read(group);
                render();
            });
        });

        render();
        root.hidden = false;

        return render;
    }

    return { mount: mount };
})();


/* ============================== classification ==============================
 *
 * Classer son arme: which regime, and what it asks of you
 */

(function () {
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

    GlabSelector.mount('[data-glab-selector="classification"]', function (state) {
        return {
            resume: 'Pour ' + ARME_LABELS[state.arme] + ', ' + BUT_LABELS[state.but] + ' :',
            cards: (RULES[state.arme + ':' + state.but] || []).map(function (key) {
                return CATALOG[key];
            }),
        };
    });
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
 * Bien choisir sa cible: which target, at which distance
 */

(function () {
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

    GlabSelector.mount('[data-glab-selector="cibles"]', function (state) {
        return {
            resume: RESUMES[state.but] + ' ' + DIST_LABELS[state.dist] + ' :',
            cards: (RULES[state.but + ':' + state.dist] || []).map(function (key) {
                return CATALOG[key];
            }),
        };
    });
})();
/* ============================== camouflage ==============================
 *
 * Choisir son camouflage: which motif, on which ground, in which season
 */

/*
 * The same selector the other three guides carry, answering a different kind
 * of question: it ranks the seven families rather than naming three products.
 * That is why a card's rank is a word here and a number there, and why the
 * engine treats every field but the title as optional.
 *
 * The families are read off the page rather than repeated in the script: the
 * markup already prints each one with its terrains and its seasons, which is
 * what a reader without JavaScript gets, and a family added to the guide is
 * added to the ranking by the same edit.
 */
(function () {
    var families = Array.prototype.slice.call(document.querySelectorAll('[data-cam-family]'));

    if (families.length === 0) {
        return;
    }

    // Snow is the honest exception: none of the seven is a winter pattern,
    // and the page says so rather than ranking seven wrong answers.
    var SNOW = 'neige';

    function listOf(el, name) {
        return (el.getAttribute(name) || '').split(' ').filter(function (value) {
            return value !== '';
        });
    }

    function capitalise(sentence) {
        return sentence.charAt(0).toUpperCase() + sentence.slice(1);
    }

    /** Two matches, one, or none: the ranking has three steps and no more. */
    function score(family, terrain, season) {
        var onTerrain = listOf(family, 'data-terrains').indexOf(terrain) !== -1;
        var inSeason = listOf(family, 'data-seasons').indexOf(season) !== -1;

        if (onTerrain && inSeason) {
            return { tier: 2, rank: 'Tient', body: 'Le motif et la saison vont avec ce terrain.', tone: 'good' };
        }

        if (onTerrain) {
            return { tier: 1, rank: 'Passe', body: 'Bon terrain, mais la saison ne joue pas pour lui.' };
        }

        if (inSeason) {
            return { tier: 1, rank: 'Passe', body: 'Bonne saison, mais il n\u2019est pas fait pour ce terrain.' };
        }

        return { tier: 0, rank: 'Peu adapté', body: 'Ni le terrain ni la saison ne sont les siens.', tone: 'poor' };
    }

    GlabSelector.mount('[data-glab-selector="camouflage"]', function (state) {
        var setting = capitalise(state.terrainPhrase) + ' ' + state.seasonPhrase + ', ';

        if (state.terrain === SNOW) {
            return {
                resume: capitalise(state.terrainPhrase)
                    + ', aucune de ces sept familles ne vaut. Un sur-vêtement blanc par-dessus '
                    + 'la tenue habituelle fait le travail, et ne sert que quelques jours par an '
                    + 'sous nos latitudes.',
                cards: [],
            };
        }

        var scored = families.map(function (family) {
            var result = score(family, state.terrain, state.season);

            result.el = family;
            result.title = family.getAttribute('data-name');

            return result;
        }).sort(function (a, b) {
            return b.tier - a.tier;
        });

        var kept = scored.filter(function (entry) {
            return entry.tier === 2;
        }).map(function (entry) {
            return entry.title;
        });

        var verdict = kept.length === 0
            ? setting + 'aucune famille ne coche les deux cases. Prenez celle qui tient le terrain et cassez la silhouette avec le relief.'
            // "CE, Woodland tiennent" is a list; "CE et Woodland tiennent" is
            // a sentence, and the verdict is written as one.
            : setting + (kept.length > 1
                ? kept.slice(0, -1).join(', ') + ' et ' + kept[kept.length - 1] + ' tiennent le mieux.'
                : kept[0] + ' tient le mieux.');

        return { resume: verdict, cards: scored };
    }, function (state, answer) {
        // The cards on the page follow the ranking, so the answer is readable
        // on the shelf of families as well as in the list.
        (answer.cards.length === 0 ? [] : answer.cards).forEach(function (entry) {
            entry.el.classList.toggle('is-picked', entry.tier === 2);
            entry.el.classList.toggle('is-dimmed', entry.tier === 0);
        });

        if (state.terrain === SNOW) {
            families.forEach(function (family) {
                family.classList.remove('is-picked');
                family.classList.add('is-dimmed');
            });
        }
    });
})();

/* ============================== entretien ==============================
 *
 * Entretenir son arme: which kit, for which calibre and which bench
 */

(function () {
    var root = document.querySelector('[data-glab-selector="entretien"]');

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

    GlabSelector.mount('[data-glab-selector="entretien"]', function (state) {
        var cards = state.lieu === 'stand'
            ? [ropeCard(state.cal), EXTRAS.etiquette, EXTRAS.recuperateur]
            : KIT_COVERS.indexOf(state.cal) !== -1
                ? [ropeCard(state.cal), EXTRAS.kit, EXTRAS.tapis]
                : [ropeCard(state.cal), EXTRAS.tapis, EXTRAS.etiquette];

        return {
            resume: 'Pour votre ' + ROPES[state.cal].label + ', '
                + (state.lieu === 'stand' ? 'au stand' : "à l'établi") + ' :',
            cards: cards,
        };
    });
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

/* ============================== optique ==============================
 *
 * Regler sa lunette : the click converter
 */

/*
 * The instrument the guide is built around.
 *
 * A reader arrives having measured a group three centimetres low, and the
 * only thing standing between that measurement and a zeroed rifle is an
 * angle-to-distance conversion nobody does in their head at the bench. So
 * the page does it: centimetres in, clicks out, once per turret.
 *
 * The ladder underneath answers the question the conversion raises, which is
 * why the same rifle takes forty clicks at ten metres and four at a hundred.
 */
(function () {
    var root = document.querySelector('[data-glab-clicks]');

    if (!root) {
        return;
    }

    // One MOA is a sixtieth of a degree and subtends 2,908 cm at 100 m; one
    // milliradian subtends exactly 10 cm at the same distance. Both are
    // angles, so what a click is worth in centimetres depends on how far the
    // target stands. That dependency is the whole point of the instrument.
    var MOA_AT_100 = 2.908;
    var MRAD_AT_100 = 10;
    var LADDER = [10, 25, 50, 100];

    var distance = root.querySelector('[data-glab-distance]');
    var turret = root.querySelector('[data-glab-turret]');
    var fields = {
        drop: {
            amount: root.querySelector('[data-glab-drop]'),
            way: root.querySelector('[data-glab-drop-way]'),
            count: root.querySelector('[data-glab-drop-count]'),
            label: root.querySelector('[data-glab-drop-label]')
        },
        drift: {
            amount: root.querySelector('[data-glab-drift]'),
            way: root.querySelector('[data-glab-drift-way]'),
            count: root.querySelector('[data-glab-drift-count]'),
            label: root.querySelector('[data-glab-drift-label]')
        }
    };
    var band = root.querySelector('[data-glab-band]');
    var ladder = Array.prototype.slice.call(root.querySelectorAll('[data-glab-rung]'));

    if (!distance || !turret || !band) {
        return;
    }

    function french(value, decimals) {
        return value.toFixed(decimals).replace('.', ',');
    }

    /** What one click of the chosen turret moves the impact, at `metres`. */
    function clickCm(metres) {
        var parts = turret.value.split('|');
        var step = parseFloat(parts[0]);
        var unit = parts[1] === 'mrad' ? MRAD_AT_100 : MOA_AT_100;

        return step * unit * (metres / 100);
    }

    // Below a centimetre the number stops being readable as a distance you
    // could mark on paper, so it changes unit rather than growing decimals.
    function span(cm) {
        return cm < 1 ? french(cm * 10, 1) + ' mm' : french(cm, 2) + ' cm';
    }

    function turn(field, metres) {
        var measured = parseFloat(String(field.amount.value).replace(',', '.'));
        var per = clickCm(metres);

        if (!isFinite(measured) || measured <= 0 || !isFinite(per) || per <= 0) {
            field.count.textContent = '0';
            field.label.textContent = 'rien à corriger';

            return;
        }

        var clicks = Math.round(measured / per);

        field.count.textContent = String(clicks);
        field.label.textContent = clicks === 1 ? 'clic ' + field.way.value : 'clics ' + field.way.value;
    }

    function render() {
        var metres = parseFloat(String(distance.value).replace(',', '.'));

        if (!isFinite(metres) || metres <= 0) {
            metres = 25;
        }

        turn(fields.drop, metres);
        turn(fields.drift, metres);

        band.textContent = 'À ' + french(metres, 0) + ' m, un clic déplace l\'impact de ' + span(clickCm(metres)) + '.';

        ladder.forEach(function (rung, index) {
            rung.textContent = span(clickCm(LADDER[index]));
        });
    }

    root.addEventListener('input', render);
    root.addEventListener('change', render);
    render();
})();

/* ============================== premiere seance ==============================
 *
 * Votre premiere seance au stand : what goes in the bag
 */

/*
 * The same two-answer selector the other guides use, answering the only
 * question a first-timer can actually act on the night before: what do I
 * put in the bag. The first answer decides most of it, because a discovery
 * session is the one visit where the right answer is « almost nothing ».
 */
(function () {
    var BAG = {
        decouverte: [
            { title: 'Une pièce d\'identité', meta: 'En cours de validité', body: 'Le club la demande pour vérifier votre inscription au fichier des interdits d\'acquisition avant de vous mettre une arme entre les mains. Sans elle, la séance ne commence pas.' },
            { title: 'Des chaussures fermées et plates', meta: 'Et un haut ajusté', body: 'Un étui à douilles chaudes trouve toujours le col d\'une chemise flottante, et une semelle plate tient l\'équilibre mieux qu\'un talon. Rien à acheter : ce que vous avez fait l\'affaire.' },
            { title: 'De quoi boire', meta: 'Une heure à une heure trente', body: 'Une séance de découverte dure entre une heure et une heure et demie, dont la moitié debout à se concentrer. Une gourde suffit.', href: '/categories/survie', cta: 'Voir les gourdes et le nécessaire' },
            { title: 'Surtout pas votre propre arme', meta: 'Même si vous en avez une', body: 'Tant que la licence n\'est pas délivrée, la séance se fait avec le matériel du club, armes et munitions comprises. Arriver avec la sienne fait perdre du temps à tout le monde.' }
        ],
        licencie: [
            { title: 'Licence et carnet de tir', meta: 'Les deux, pas l\'un', body: 'La licence en cours de validité ouvre le pas de tir et vaut motif légitime pour le trajet. Le carnet reçoit le visa de la séance, et ce sont ces visas qui comptent plus tard.' },
            { title: 'Un tapis ou un sac de tir', meta: 'Le club prête rarement le confort', body: 'Le poste couché se joue sur la stabilité, et un tapis vaut plusieurs séances d\'entraînement. Un trépied rend le même service à la lunette d\'observation.', href: '/categories/kit-stand-tir', cta: 'Voir le kit de stand' },
            { title: 'Vos cibles', meta: 'Le club en vend, rarement les vôtres', body: 'Un stand fournit ses cartons réglementaires ; les cibles réactives et les planches d\'entraînement, c\'est vous qui les apportez.', href: '/categories/cibles', cta: 'Voir les cibles' },
            { title: 'Une boîte à munitions', meta: 'Fermée, séparée de l\'arme', body: 'Elle range, elle compte, et elle vous évite de fouiller un sac au milieu d\'une série. Sur le trajet, elle voyage à part de l\'arme.', href: '/categories/boites-munitions', cta: 'Voir les boîtes' }
        ],
        propre: [
            { title: 'Une housse ou une mallette fermée', meta: 'Arme déchargée, munitions à part', body: 'Entre chez vous et le stand, l\'arme voyage déchargée, dans un étui fermé, munitions rangées séparément, la licence valant motif légitime. Ce n\'est pas une recommandation de club, c\'est la condition du transport.', href: '/categories/housses-fourreaux-mallettes', cta: 'Voir les housses et mallettes' },
            { title: 'Un témoin de chambre vide', meta: 'Ce que le voisin de pas de tir regarde', body: 'Drapeau ou étiquette, il dit à trois mètres que la chambre est ouverte et vide. Beaucoup de clubs le rendent obligatoire au râtelier, et personne ne vous en prêtera un.', href: '/categories/temoin-de-chambre-vide', cta: 'Voir les témoins' },
            { title: 'Vos munitions, dans leur boîte', meta: 'Un seul lot par séance', body: 'Un lot par séance, noté quelque part : c\'est la seule façon de savoir plus tard si un groupement qui s\'ouvre vient de vous ou du lot.', href: '/categories/munitions', cta: 'Voir les munitions' },
            { title: 'De quoi nettoyer en rentrant', meta: 'Le soir même', body: 'Le nettoyage se fait le jour du tir, pas le week-end suivant. Une corde suffit pour un canon, un kit à tiges pour le reste.', href: '/categories/entretien-arme', cta: 'Voir l\'entretien' }
        ]
    };

    // What the discipline adds to the bag, whatever the visit: one card, in
    // the rayon the shop actually stocks for it.
    var EXTRA = {
        air: { title: 'Vos plombs, et une cible à grille', meta: 'Air comprimé, 10 m', body: 'À dix mètres tout se joue sur le lot de plombs et sur ce que vous savez lire du carton. Une cible à grille transforme un groupement décentré en un nombre de clics.', href: '/categories/cibles-carrees', cta: 'Voir les cibles à grille' },
        poing: { title: 'Un récupérateur de douilles', meta: 'Pistolet, 25 m', body: 'Il tient le pas de tir propre, il vous évite de ramper sous la tablette du voisin, et il rend les étuis récupérables pour qui recharge.', href: '/categories/recuperateurs-de-douilles', cta: 'Voir les récupérateurs' },
        carabine: { title: 'Une longue-vue', meta: 'Carabine, 50 m et plus', body: 'À cinquante mètres, on ne voit plus ses impacts à l\'œil nu, et on ne descend pas au but quand on veut. Une longue-vue sur trépied vous rend la séance.', href: '/categories/longues-vues', cta: 'Voir les longues-vues' }
    };

    GlabSelector.mount('[data-glab-selector="seance"]', function (state) {
        var cards = (BAG[state.visite] || BAG.decouverte).slice(0);

        // A discovery session is the one visit where adding gear is the
        // wrong advice: the club supplies it, and the guide says so instead
        // of selling into it.
        if (state.visite !== 'decouverte' && EXTRA[state.arme]) {
            cards.push(EXTRA[state.arme]);
        }

        return {
            resume: 'Pour ' + state.visitePhrase + ' ' + state.armePhrase + ' :',
            cards: cards
        };
    });
})();
