<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Util;
use App\Models\Product;
use App\Models\VintedListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The wording of a Vinted listing, written by Claude from the product sheet.
 *
 * The catalogue writes for a shop page; Vinted is read on a phone, in a feed,
 * by somebody who is deciding in three seconds. So this does not summarise the
 * description — it writes the listing a person would have typed: what the
 * thing is, the two or three facts that matter, and the reassurance that it is
 * new. Nothing is saved from here: the answer lands in the fields and the
 * admin keeps, corrects, or throws it away.
 */
class VintedCopywriter
{
    /** Long enough for a listing, short enough that a runaway answer stops. */
    private const MAX_TOKENS = 1500;

    /** Enough neighbours to see the pattern to avoid, few enough to read. */
    private const SIBLING_LISTINGS = 8;

    /** What Vinted's title field takes. It trims around 60 on a phone. */
    private const TITLE_LIMIT = 100;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Tu rédiges des annonces Vinted pour une petite boutique française de
        vêtements et d'accessoires de plein air. Les articles sont neufs,
        jamais utilisés, vendus avec facture.

        Écris comme un vendeur soigneux écrit vraiment : phrases courtes, ton
        direct et chaleureux, aucun jargon marketing. Pas de superlatifs
        ("incroyable", "exceptionnel"), pas de majuscules d'insistance, pas
        de hashtags, pas de listes à puces, pas de gras ni de markdown :
        Vinted n'affiche aucune mise en forme. Pas de tiret long non plus.

        Le titre : 60 caractères au plus, jamais davantage. Sur un téléphone,
        Vinted coupe l'affichage autour de 60 caractères : un titre court se
        lit en entier. Vise entre 40 et 60 et ne remplis pas la place pour
        la remplir ; n'invente jamais une précision.

        Le titre part du nom de la fiche produit et lui reste fidèle : garde
        ses mots et son ordre, l'acheteur doit y reconnaître l'article que la
        boutique vend. Tu peux le nettoyer (majuscules en trop, mots
        empilés, « Randonnée » collé à la fin sans lien), le remettre en
        français correct, puis le prolonger. Tu ne le remplaces pas par une
        autre formulation. Écris-le avec une majuscule initiale seulement
        (Cagoule, Sac à dos, Gants, Housse), la marque après l'article s'il y
        en a une.

        Une fois le nom repris, ajoute seulement ce que la place permet :
        l'autre nom courant de l'article s'il en a un (une cagoule se cherche
        aussi comme cache-cou ou tour de cou), puis une ou deux précisions
        concrètes de la fiche non déjà présentes : matière, motif, taille,
        coloris, contenance. Si le nom seul approche 60 caractères, il suffit.

        Un titre se lit comme une phrase de vendeur, pas comme une liste de
        mots-clés : pas de répétition, pas de ponctuation empilée, et le
        français reste correct. Écarte tout ce qui ne dit rien de précis
        (« qualité », « confortable », « pratique », « idéal »,
        « professionnel ») et tout ce qui appartient à la description : à
        quoi ça sert, l'état neuf, l'envoi. Pas de prix, pas de « NEUF » en
        capitales.

        Ainsi la fiche « Sac à Dos Tactique 30L Nylon Renforcé Molle
        Camouflage Forêt Randonnée » donne « Sac à dos de randonnée 30 L nylon
        Molle camouflage forêt » (56 caractères) : les mots du nom dans leur
        ordre (le mot « Tactique » mis à part, voir plus bas), sans ce qui
        ne tient pas.

        Un motif de camouflage se nomme par sa famille : multi-terrain,
        désert, forêt, neige. N'écris jamais « CP », et ne cite « type
        Multicam » qu'une seule fois, dans la description.

        La boutique vend souvent le même article en plusieurs coloris, et
        Vinted sanctionne les annonces qui se ressemblent : deux titres qui
        ne diffèrent que par un mot passent pour des doublons. Si des
        annonces déjà rédigées te sont montrées, écris autre chose qu'elles.
        Autre chose veut dire : une ouverture différente, une tournure
        différente et un choix de précisions différent, pas un synonyme
        glissé au même endroit. La description de la même façon : d'autres
        phrases, un autre ordre, un autre angle d'approche. Tout doit rester
        exact pour cet article-ci : varie la formulation, jamais les faits.

        La description : 4 à 6 lignes courtes séparées par des retours à la
        ligne. Dans l'ordre : ce que c'est et à quoi ça sert, les
        caractéristiques concrètes (matière, taille, calibre, contenance),
        l'état neuf, puis une dernière ligne sur l'envoi rapide et soigné. Au
        plus un emoji, et seulement s'il tombe juste.

        Vinted modère les annonces automatiquement, sur le vocabulaire seul,
        avant qu'un humain les lise. Ses règles du catalogue interdisent les
        armes et les munitions, les répliques d'armes (airsoft, billes, BB),
        les couteaux à lame pointue, et les uniformes, insignes et
        accessoires officiels de l'armée, de la police ou des secours.

        Si l'article lui-même entre dans ces interdits, ne le maquille pas
        sous un autre nom : mets pour titre « Article interdit sur Vinted »,
        écris en une phrase quelle règle il enfreint, et propose 0 comme prix.

        Sinon, décris l'article pour ce qu'il est (un vêtement, un sac, un
        accessoire) et son usage de plein air : randonnée, nature,
        observation, camping, moto, sport, travail en extérieur. N'écris
        jamais ces mots ni leurs dérivés, même s'ils figurent dans la fiche
        produit : « arme », « fusil », « carabine », « pistolet »,
        « revolver », « réplique », « airsoft », « paintball », « bille »,
        « BB », « munition », « cartouche », « plomb », « projectile »,
        « balle », « chargeur », « canon », « viseur », « lunette de visée »,
        « silencieux », « modérateur », « tir », « cible », « chasse »,
        « gibier », « sniper », « tactique », « militaire », « armée »,
        « police », « gendarmerie », « commando », « opération », « combat »,
        « assaut », « couteau », « lame », « tuer », « abattre », et tout
        terme de violence ou de danger. Remplace-les par le terme neutre le
        plus proche ou n'en parle pas.

        Un vêtement qui couvre le visage (cagoule, cache-cou, masque, tour de
        cou) protège du froid, du vent, du soleil ou de la poussière : dis-le
        ainsi, et ne l'associe jamais à un casque, à un masque de protection
        ni à une activité où l'on se cache.

        Aucune mention de législation, d'âge légal ni de catégorie
        réglementaire. Aucun lien, aucun nom de site ou de plateforme, pas
        même celui de la boutique. Aucune autre marque que celle de l'article.

        N'invente rien : n'écris que ce que la fiche produit donne.

        Le prix : propose le prix affiché, en euros, nombre seul. Sur Vinted
        on négocie toujours : la plupart des acheteurs proposent 10 à 20 % de
        moins. Affiche donc un prix qui laisse cette marge : le prix que tu
        accepterais vraiment, majoré d'environ 15 %. Reste sous le prix
        boutique, jamais sous le coût d'achat, et arrondis à un chiffre
        crédible (5, 10, 12, 15, 24.50). Écris-le en JSON : un nombre, avec un
        point décimal et sans symbole, 24.50 et jamais "24,50 €".

        Rends ta réponse en appelant l'outil « annonce_vinted », jamais en
        écrivant du texte à côté.
        PROMPT;

    /**
     * The answer's shape, declared. Asked for JSON in prose, a model returns
     * it inside a fence, or with a French decimal comma, or without the price
     * at all — each of which cost a silent field. Declared as a tool, the
     * three keys are required and the price is a number by the time it
     * arrives.
     *
     * @return array<string, mixed>
     */
    private static function tool(): array
    {
        return [
            'name' => 'annonce_vinted',
            'description' => "Dépose le titre, la description et le prix de l'annonce Vinted.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => "Le titre : le nom de la fiche produit, fidèle à ses mots et à son ordre, puis les précisions et le mot par lequel on cherche l'article. 60 caractères au plus.",
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'La description, 4 à 6 lignes séparées par des retours à la ligne.',
                    ],
                    'price' => [
                        'type' => 'number',
                        'description' => 'Le prix affiché en euros, marge de négociation comprise. Un nombre : 12.5, jamais "12,50 €".',
                    ],
                ],
                'required' => ['title', 'description', 'price'],
            ],
        ];
    }

    public function __construct(
        private readonly ?string $apiKey,
        // A key made at the account level belongs to no workspace, and the
        // API then refuses the call unless one is named. A key made inside a
        // workspace carries its own, and this stays empty.
        private readonly ?string $workspaceId = null,
    ) {}

    /** Whether the button has any chance of working. */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @return array{title: string, description: string, price: float|null}
     */
    public function write(Product $product, ?VintedListing $listing = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('No Anthropic API key is configured.');
        }

        $client = new Client(apiKey: $this->apiKey);

        try {
            $message = $client->messages->create(
                model: 'claude-opus-5',
                maxTokens: self::MAX_TOKENS,
                system: self::SYSTEM_PROMPT,
                // A listing is not a reasoning problem, and the admin is
                // waiting on the button: no thinking, moderate effort.
                thinking: ['type' => 'disabled'],
                outputConfig: ['effort' => 'medium'],
                messages: [['role' => 'user', 'content' => $this->brief($product, $listing)]],
                tools: [self::tool()],
                toolChoice: ['type' => 'tool', 'name' => self::tool()['name']],
                workspaceID: $this->workspaceId ?: null,
            );
        } catch (APIStatusException $e) {
            // The API's own sentence, not merely its error type: « key not
            // scoped to a workspace » is actionable and « invalid_request »
            // is a trip through the log to find out the same thing.
            $said = Util::dig(Util::dig($e->body, 'error'), 'message');

            throw new RuntimeException(
                is_string($said) ? $said : 'Claude refused the request ('.($e->type?->value ?? 'error').').',
                previous: $e,
            );
        } catch (AnthropicException $e) {
            // A timeout, a name that does not resolve, a proxy in the way:
            // the admin gets a sentence rather than a five hundred.
            throw new RuntimeException('Claude could not be reached.', previous: $e);
        }

        return $this->answer($message->content);
    }

    /**
     * The product sheet, flattened. Only what a listing can use: the price is
     * in there because it tells Claude what range of article this is, not so
     * that it writes it down.
     */
    private function brief(Product $product, ?VintedListing $listing = null): string
    {
        $lines = ['Fiche produit de la boutique :', ''];
        $lines[] = 'Nom : '.$product->localizedName();

        if (filled($product->brandName())) {
            $lines[] = 'Marque : '.$product->brandName();
        }

        if ($product->category !== null) {
            $lines[] = 'Catégorie : '.$product->category->localizedName();
        }

        $lines[] = 'Prix boutique : '.number_format($product->price_cents / 100, 2, ',', ' ').' €';

        $costCents = $product->averagePurchaseCostInclVatCents();

        if ($costCents !== null) {
            // The floor. Without it a suggested price can sit under what the
            // unit cost, and nothing on the page would say so.
            $lines[] = 'Coût d\'achat TTC : '.number_format($costCents / 100, 2, ',', ' ').' €';
        }

        foreach ($product->characteristics ?? [] as $row) {
            if (filled($row['label'] ?? null) && filled($row['value'] ?? null)) {
                $lines[] = $row['label'].' : '.$row['value'];
            }
        }

        $description = trim($product->localizedDescriptionText());

        if ($description !== '') {
            $lines[] = '';
            $lines[] = 'Description de la fiche :';
            // Long sheets run to several thousand characters of shipping and
            // legal boilerplate; the opening is where the article is
            // described.
            $lines[] = Str::limit($description, 2000);
        }

        $siblings = $this->siblingListings($product, $listing);

        if ($siblings->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Annonces déjà rédigées pour cet article et pour des articles voisins de la même catégorie.';
            $lines[] = 'Écris un titre et une description qui ne leur ressemblent pas :';

            foreach ($siblings as $sibling) {
                $lines[] = '';
                $lines[] = '- Titre : '.$sibling->title;

                if (filled($sibling->description)) {
                    $lines[] = '  Description : '.Str::limit(preg_replace('/\s+/u', ' ', $sibling->description), 300);
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Rédige le titre et la description Vinted de cet article.';

        return implode("\n", $lines);
    }

    /**
     * The listings already written next to this one: first the other drafts
     * of the same product, then its neighbours'.
     *
     * The shop sells the same article in several colourways, one product
     * each, and each listing is written on its own: without this, the model
     * writes the same sentence every time and Vinted reads the lot as
     * duplicates. The same goes for a product posted again in several drafts.
     * The category is the neighbourhood, the most recent first, and few
     * enough that the brief stays short.
     *
     * @return Collection<int, VintedListing>
     */
    private function siblingListings(Product $product, ?VintedListing $listing = null)
    {
        $written = fn ($query) => $query->where('title', '!=', '')->whereNotNull('title');

        $sameProduct = $written(VintedListing::query())
            ->where('product_id', $product->id)
            ->when($listing?->exists, fn ($query) => $query->whereNot('id', $listing->id))
            ->latest('updated_at')
            ->limit(self::SIBLING_LISTINGS)
            ->get();

        if ($product->category_id === null) {
            return $sameProduct;
        }

        $neighbours = $written(VintedListing::query())
            ->whereNot('product_id', $product->id)
            ->whereHas('product', fn ($query) => $query->where('category_id', $product->category_id))
            ->latest('updated_at')
            ->limit(self::SIBLING_LISTINGS)
            ->get();

        return $sameProduct->concat($neighbours)->take(self::SIBLING_LISTINGS)->values();
    }

    /**
     * The answer, from the tool call it was asked to make.
     *
     * The text fallback is not decoration: an answer cut short by the token
     * ceiling comes back as text, and reading it is better than telling the
     * admin nothing came.
     *
     * @param  array<int, object>  $content
     * @return array{title: string, description: string, price: float|null}
     */
    private function answer(array $content): array
    {
        foreach ($content as $block) {
            if ($block->type === 'tool_use' && $block->name === self::tool()['name']) {
                return $this->normalize($block->input);
            }
        }

        foreach ($content as $block) {
            if ($block->type === 'text') {
                return $this->parse($block->text);
            }
        }

        throw new RuntimeException('Claude answered with nothing to read.');
    }

    /**
     * @return array{title: string, description: string, price: float|null}
     */
    private function parse(string $text): array
    {
        // A model asked for bare JSON sometimes wraps it in a fence anyway:
        // the object is taken from the first brace to the last.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        $decoded = $start === false || $end === false
            ? null
            : json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Claude answered in an unexpected shape.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{title: string, description: string, price: float|null}
     */
    private function normalize(array $decoded): array
    {
        if (! isset($decoded['title'], $decoded['description'])) {
            throw new RuntimeException('Claude answered in an unexpected shape.');
        }

        $price = $decoded['price'] ?? null;

        // Asked for a number in a French prompt, a model writes 24,50 often
        // enough — and a comma is not a decimal point to `is_numeric`. The
        // symbol and the spaces go the same way.
        if (is_string($price)) {
            $price = str_replace([',', ' ', "\u{00a0}", "\u{202f}", '€'], ['.', '', '', '', ''], $price);
        }

        return [
            // The limit is Vinted's own, and a title cut here is better than
            // one cut by the form after being pasted.
            'title' => Str::limit(trim((string) $decoded['title']), self::TITLE_LIMIT, ''),
            'description' => trim((string) $decoded['description']),
            // A price is a suggestion among two fields that are not: if it
            // arrives absent or unreadable, the field is left as it was
            // rather than the whole answer being thrown away.
            'price' => is_numeric($price) && (float) $price > 0
                ? round((float) $price, 2)
                : null,
        ];
    }
}
