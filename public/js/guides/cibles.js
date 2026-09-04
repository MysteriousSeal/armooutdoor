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
            meta: '51 à 76 mm · lots de 200',
            body: "La grille donne la correction en clics, ligne par ligne, colonne par colonne. La cible du réglage, pas celle du score.",
            href: '/categories/cibles-carrees',
            cta: 'Voir les carrées',
        },
        metal: {
            title: 'Cible basculante 5 plaques',
            meta: 'Acier · réarmement automatique',
            body: "Sonne à chaque plaque touchée et se relève seule. Aucun consommable, pour le tir ludique aux airguns et au 22 LR.",
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
