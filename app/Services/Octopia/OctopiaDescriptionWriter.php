<?php

namespace App\Services\Octopia;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Util;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The title and the description a product is sent to Cdiscount with, written
 * by Claude.
 *
 * Octopia files a product by reading its title and its description. The
 * shop's are written for a product page: the description lists every use of
 * the article, and a product filed as a cap came back re-categorised because
 * its text spoke of other things. These are written for the category the seller
 * chose. They say what the article is, as that category names it, and what the
 * product sheet says of it, and stop there. They are kept apart from the
 * shop's and sent instead.
 *
 * It does not choose the category and does not steer the product towards
 * another: the category is the seller's, and a title or a description that made
 * an article read as some other kind of article would be false. Nothing is
 * saved from here; the answer lands in the fields and the admin keeps or
 * corrects it.
 */
class OctopiaDescriptionWriter
{
    /** Octopia takes 2000 characters; a description this short is read whole. */
    public const LIMIT = 2000;

    /** What Octopia's title field takes. */
    public const TITLE_LIMIT = 132;

    /**
     * Reading facts off a product sheet and phrasing them plainly is not a job
     * for the largest model: the faster one does it, and the admin is waiting
     * on the button.
     */
    private const MODEL = 'claude-sonnet-5';

    private const MAX_TOKENS = 1200;

    private const SOURCE_LIMIT = 3000;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Tu rédiges le titre et la description d'un produit pour Cdiscount, pour une petite
        boutique française d'articles de plein air. Les articles sont neufs.

        Cdiscount range un produit d'après son titre et sa description, et la
        catégorie a déjà été choisie par le vendeur. Ton titre et ta
        description doivent donc être cohérents avec cette catégorie :
        présente l'article pour ce que la catégorie nomme, et pas pour autre
        chose. Une casquette est décrite comme une casquette, un sac à dos
        comme un sac à dos. Ne le présente jamais comme un autre type
        d'article que celui de la catégorie.

        Le titre : entre 50 et 110 caractères, 132 au maximum. Il commence par
        ce qu'est l'article, tel que la catégorie le nomme, puis la marque
        s'il y en a une, puis les précisions que la fiche donne : matière,
        motif ou coloris, taille, contenance, dimensions. Il se lit comme un
        libellé de catalogue, pas comme une phrase : pas de ponctuation
        empilée, pas de majuscules d'insistance, pas de mots vagues
        (« qualité », « idéal », « pratique »), pas de prix, pas de nom de
        boutique. N'y mets pas d'usage ni d'activité : ils ne servent pas à
        identifier l'article et font ranger le produit ailleurs.

        Reste factuel et sobre. Commence par dire ce qu'est l'article, puis
        donne ce que la fiche établit : matière, coupe, finitions, coloris ou
        motif, dimensions, poids. Trois à cinq phrases, entre 250 et 700
        caractères, en texte simple, sans liste à puces, sans HTML, sans
        markdown.

        N'énumère pas les activités ou les usages de l'article : une liste
        d'usages fait lire l'article comme un autre produit. Un seul usage,
        le principal, s'il est utile ; sinon aucun. Ne cite pas d'autres
        types de produits. Ne reprends pas les rubriques « Style »,
        « Utilisation », « Sports » ou « Activités » de la fiche : ce sont
        des listes d'usages, pas la description de l'article.

        Chaque affirmation de ta description doit se retrouver dans la fiche,
        presque mot pour mot : si tu ne peux pas la rattacher à une ligne de
        la fiche, tu ne l'écris pas. N'ajoute aucune situation d'usage
        (« sous un casque », « en voyage »), aucun accessoire ni équipement
        associé à l'article, aucune comparaison avec d'autres produits.

        N'invente rien : n'écris que ce que la fiche produit donne, et une
        information que la fiche ne donne pas n'a pas sa place ici, même si
        elle est courante pour ce type d'article. En particulier aucun conseil
        d'entretien ni de lavage, aucune promesse de durabilité, de
        résistance, d'étanchéité ou de performance, aucune norme et aucune
        provenance que la fiche ne cite pas. Pas de superlatif ni de promesse (« idéal », « parfait », « le meilleur »),
        pas de majuscules d'insistance, pas de prix, pas de mention de
        livraison, de garantie ou de stock. Aucun lien, aucun nom de boutique
        ni de site, aucune marque autre que celle de l'article. Pas de tiret
        long.

        Rends ta réponse en appelant l'outil « fiche_cdiscount », jamais en
        écrivant du texte à côté.
        PROMPT;

    /** @return array<string, mixed> */
    private static function tool(): array
    {
        return [
            'name' => 'fiche_cdiscount',
            'description' => 'Dépose le titre et la description du produit pour Cdiscount, cohérents avec la catégorie choisie.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => "Le titre : ce qu'est l'article tel que la catégorie le nomme, puis la marque et les précisions de la fiche. Entre 50 et 110 caractères, 132 au maximum.",
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'La description : trois à cinq phrases en texte simple, entre 250 et 700 caractères.',
                    ],
                ],
                'required' => ['title', 'description'],
            ],
        ];
    }

    public function __construct(
        private readonly ?string $apiKey,
        // A key made at the account level belongs to no workspace, and the
        // API then refuses the call unless one is named.
        private readonly ?string $workspaceId = null,
    ) {}

    /** Whether the button has any chance of working. */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @return array{title: string, description: string}
     */
    public function write(Product $product, OctopiaTemplate $template): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('No Anthropic API key is configured.');
        }

        $client = new Client(apiKey: $this->apiKey);

        try {
            $message = $client->messages->create(
                model: self::MODEL,
                maxTokens: self::MAX_TOKENS,
                system: self::SYSTEM_PROMPT,
                thinking: ['type' => 'disabled'],
                outputConfig: ['effort' => 'medium'],
                messages: [['role' => 'user', 'content' => $this->brief($product, $template)]],
                tools: [self::tool()],
                toolChoice: ['type' => 'tool', 'name' => self::tool()['name']],
                workspaceID: $this->workspaceId ?: null,
            );
        } catch (APIStatusException $e) {
            $said = Util::dig(Util::dig($e->body, 'error'), 'message');

            throw new RuntimeException(
                is_string($said) ? $said : 'Claude refused the request ('.($e->type?->value ?? 'error').').',
                previous: $e,
            );
        } catch (AnthropicException $e) {
            throw new RuntimeException('Claude could not be reached.', previous: $e);
        }

        foreach ($message->content as $block) {
            if ($block->type === 'tool_use' && $block->name === self::tool()['name']) {
                $title = $this->cleanTitle((string) ($block->input['title'] ?? ''));
                $description = $this->clean((string) ($block->input['description'] ?? ''));

                if ($title === '' || $description === '') {
                    throw new RuntimeException('Claude answered with an empty title or description.');
                }

                return ['title' => $title, 'description' => $description];
            }
        }

        throw new RuntimeException('Claude answered with nothing to read.');
    }

    /**
     * The product sheet and the category it is described for, flattened.
     */
    private function brief(Product $product, OctopiaTemplate $template): string
    {
        $lines = ['Catégorie Cdiscount choisie par le vendeur : '.$template->name, '', 'Fiche produit de la boutique :'];
        $lines[] = 'Nom : '.$product->localizedName();

        if (filled($product->brandName())) {
            $lines[] = 'Marque : '.$product->brandName();
        }

        if ($product->weight_grams) {
            $lines[] = 'Poids : '.$product->weight_grams.' g';
        }

        foreach (array_merge($product->characteristics ?? [], $product->filter_attributes ?? []) as $row) {
            if (filled($row['label'] ?? null) && filled($row['value'] ?? null)) {
                $lines[] = $row['label'].' : '.$row['value'];
            }
        }

        $source = trim($product->localizedDescriptionText());

        if ($source !== '') {
            $lines[] = '';
            $lines[] = 'Description de la fiche (à reformuler, sans en reprendre la liste des usages) :';
            $lines[] = Str::limit($source, self::SOURCE_LIMIT);
        }

        $lines[] = '';
        $lines[] = 'Rédige le titre et la description Cdiscount de cet article, cohérents avec la catégorie choisie.';

        return implode("\n", $lines);
    }

    /**
     * The title as it will be sent: one line of plain text, within what
     * Octopia's field takes.
     */
    public function cleanTitle(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = str_replace(["\u{2014}", "\u{2013}"], '-', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit(trim($text), self::TITLE_LIMIT, '');
    }

    /**
     * The description as it will be sent: plain, on the lines it was written on, and
     * within what Octopia takes. Octopia refuses HTML in this field.
     */
    public function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        // No long dash, in a French text a hyphen or a comma does as well.
        $text = str_replace(["\u{2014}", "\u{2013}"], '-', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;

        return Str::limit(trim($text), self::LIMIT, '');
    }
}
