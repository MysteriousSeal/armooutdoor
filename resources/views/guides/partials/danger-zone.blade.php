{{-- The lane, seen from above.

     Every rule on this page is one question drawn: what stands behind the
     target when the projectile goes through it, or past it. The cone is
     the part nobody pictures, and it is the part the law is about. --}}
<figure class="glab-lane">
    <div class="glab-lane-frame">
    <svg viewBox="0 0 640 320" role="img" aria-labelledby="lane-title lane-desc">
        <title id="lane-title">Vue de dessus d'une ligne de tir et de sa zone dangereuse</title>
        <desc id="lane-desc">
            Depuis le poste de tir, un cône s'ouvre vers la cible et se prolonge
            au-delà d'elle. Le point d'arrêt referme ce cône. Sans lui, la zone
            dangereuse continue jusqu'à la limite de la propriété et au-delà,
            vers la maison voisine et le chemin.
        </desc>

        {{-- What lies beyond, and would be reached without a backstop. --}}
        <path class="glab-lane-cone" d="M64 160 L640 44 L640 276 Z" />
        <path class="glab-lane-safe" d="M64 160 L392 94 L392 226 Z" />

        <line class="glab-lane-axis" x1="64" y1="160" x2="640" y2="160" stroke-dasharray="6 7" />

        {{-- The boundary of the property: the cone does not stop at it. --}}
        <line class="glab-lane-fence" x1="472" y1="16" x2="472" y2="304" />
        <text class="glab-lane-label" x="480" y="30">Limite de propriété</text>

        {{-- The firing point. --}}
        <circle class="glab-lane-shooter" cx="64" cy="160" r="9" />
        <text class="glab-lane-label" x="64" y="192" text-anchor="middle">Poste</text>

        {{-- The target, then the thing that actually matters. --}}
        <rect class="glab-lane-target" x="352" y="132" width="8" height="56" />
        <text class="glab-lane-label" x="356" y="120" text-anchor="middle">Cible</text>

        <rect class="glab-lane-stop" x="380" y="94" width="14" height="132" />
        <text class="glab-lane-label glab-lane-label--key" x="387" y="248" text-anchor="middle">Point d'arrêt</text>

        {{-- What the cone would run into if the backstop were not there. --}}
        <path class="glab-lane-house" d="M556 52 h56 v44 h-56 z M556 52 l28 -20 l28 20" />
        <text class="glab-lane-label" x="584" y="112" text-anchor="middle">Habitation</text>

        <line class="glab-lane-path" x1="524" y1="248" x2="640" y2="284" />
        <text class="glab-lane-label" x="566" y="272">Chemin</text>
    </svg>
    </div>

    <figcaption>
        Le cône ne s'arrête pas à la cible, et pas davantage à la clôture. Ce qui
        l'arrête, c'est le point d'arrêt : un tir n'est sûr que si ce qui se
        trouve derrière la cible accepte de recevoir le projectile.
    </figcaption>
</figure>
