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
        22: { label: '.22 LR · 5,56', slug: 'corde-nettoyage-canon-22-223-5-56mm-bore-rope', family: '.22 · .223 · 5,56 mm' },
        25: { label: '.25 · 6,35 mm', slug: 'corde-nettoyage-canon-carabine-25-264-635mm-bore-rope', family: '.25 · .264 · 6,35 mm' },
        9: { label: '9 mm · .38 · .357', slug: 'corde-nettoyage-canon-38-357-380-9mm-bore-rope', family: '.38 · .357 · .380 · 9 mm' },
        308: { label: '.308 · 7,62', slug: 'corde-nettoyage-canon-carabine-30-308-30-06-300-303-7-62mm-bore-rope', family: '.30 · .308 · 7,62 mm' },
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

            var title = document.createElement('h4');
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
