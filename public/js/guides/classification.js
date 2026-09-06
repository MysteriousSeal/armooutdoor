/*
 * The classification guide's selector: an energy band and a question,
 * three doors into the shop's own articles on categories D, C and A.
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
        'c:acheter': ['c', 'd', 'a'],
        'c:transporter': ['c', 'd', 'a'],
        'c:risque': ['c', 'a', 'd'],
        'b:acheter': ['a', 'c', 'd'],
        'b:transporter': ['a', 'c', 'd'],
        'b:risque': ['a', 'c', 'd'],
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
