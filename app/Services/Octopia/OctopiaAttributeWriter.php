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
 * The answers to a Cdiscount category's attributes, drawn by Claude from what
 * the product sheet already says.
 *
 * Octopia asks a category a few dozen things: material, colour, hat size,
 * gender, care instructions. Most of them are written on the sheet, in the
 * title, the description, the characteristics or the weight. This reads them
 * out, and nothing more: an attribute the sheet does not establish is left
 * empty for the seller, since a plausible guess put in front of Octopia is
 * worse than a blank one. Nothing is saved from here either: the answers land
 * in the form and the admin keeps, corrects or clears them.
 */
class OctopiaAttributeWriter
{
    /** Room for a few dozen short answers, not for a runaway one. */
    private const MAX_TOKENS = 4000;

    /** The description is long on some sheets; its opening says what the article is. */
    private const DESCRIPTION_LIMIT = 3000;

    /** A closed list can run to hundreds of colours: enough to choose from, not all of them. */
    private const OPTIONS_LIMIT = 400;

    /**
     * The attributes the shop lets Claude answer even when the sheet is
     * silent, with the rule it answers by. Anything else is answered only
     * when the sheet states it.
     *
     * They are the ones a seller of outdoor accessories answers the same way
     * nearly every time, and which the sheet rarely spells out: who wears it,
     * what size it comes in, how it is washed. A value given on this footing
     * is marked as assumed, so the seller reads it over instead of taking it
     * for a fact of the sheet. The origin is here on the seller's word: it is
     * a statement to the customer, and an assumed one is checked before Save.
     * Keyed by Octopia's property reference.
     *
     * @var array<string, string>
     */
    public const ASSUMABLE = [
        '28003' => 'Mixte quand la fiche ne désigne ni les hommes, ni les femmes, ni les enfants.',
        '24097' => "Adulte quand la fiche ne parle ni d'enfants ni de bébés.",
        '28898' => 'La famille du sport principal parmi les usages que la fiche cite : le premier, ou celui qui domine.',
        '46831' => 'Taille unique pour un accessoire vendu sans taille (cagoule, bonnet, tour de cou), sauf si la fiche donne des tailles.',
        '11429' => "Le pays de fabrication si la fiche le dit ; sinon celui où ce type d'article est le plus souvent fabriqué.",
        '25233' => "Les conseils d'entretien usuels de la matière indiquée sur la fiche.",
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Tu remplis les attributs d'une fiche produit Cdiscount, pour une petite
        boutique française d'articles de plein air. Les articles sont neufs.

        Tu ne disposes que de la fiche produit de la boutique : son nom, sa
        marque, sa description, ses caractéristiques, son poids, ses
        variantes. N'écris une valeur que si cette fiche l'établit, en toutes
        lettres ou par une conséquence évidente (un « 100 % polyester » donne
        la matière). Ce qui n'est pas sur la fiche reste vide : tu n'inventes
        rien, tu ne supposes rien, tu ne complètes pas avec ce qui est
        courant pour ce type d'article. Un attribut laissé vide est une bonne
        réponse ; une valeur plausible mais non écrite est une mauvaise
        réponse, parce que Cdiscount la publie.

        Pour un attribut à liste fermée, la valeur est exactement l'une des
        options proposées, recopiée à l'identique. Si aucune ne correspond
        à ce que dit la fiche, tu ne réponds pas : tu ne prends jamais
        l'option « la plus proche ».

        Pour un attribut à plusieurs valeurs, sépare-les par des points-virgules.
        Pour un attribut numérique, écris le nombre seul, avec un point
        décimal, dans l'unité indiquée : convertis le poids de la fiche, qui est
        en grammes, si l'attribut est en kilogrammes.

        Une exception : certains attributs portent la mention « peut être
        supposé », avec la règle à suivre. Pour ceux-là seulement, si la fiche
        ne dit rien, réponds ce que la règle indique, et déclare ta réponse
        « supposé ». Une valeur que la fiche établit est déclarée « fiche »,
        même pour ces attributs. Pour tous les autres, une valeur supposée est
        interdite : sans la fiche, tu ne réponds pas.

        Un attribut marqué « par variante » a une réponse propre à chaque
        variante : donne-la avec l'identifiant de la variante, d'après son
        libellé. Pour les autres, réponds une seule fois.

        Écris en français, sans majuscule d'insistance, sans tiret long.
        Rends ta réponse en appelant l'outil « fiche_cdiscount », jamais en
        écrivant du texte à côté.
        PROMPT;

    /**
     * The answer's shape, declared: a list of code and value pairs, since the
     * codes are Octopia's and differ from one category to the next.
     *
     * @return array<string, mixed>
     */
    private static function tool(): array
    {
        return [
            'name' => 'fiche_cdiscount',
            'description' => "Dépose les valeurs des attributs que la fiche produit permet d'établir. N'y figure aucun attribut dont la réponse n'est pas sur la fiche.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'values' => [
                        'type' => 'array',
                        'description' => 'Les attributs qui ont une seule réponse pour le produit.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'code' => ['type' => 'string', 'description' => "Le code de l'attribut, tel qu'il est donné."],
                                'value' => ['type' => 'string', 'description' => 'La valeur.'],
                                'basis' => [
                                    'type' => 'string',
                                    'enum' => ['fiche', 'supposé'],
                                    'description' => "« fiche » si la fiche produit établit cette valeur, « supposé » si tu l'as déduite d'une règle « peut être supposé ».",
                                ],
                            ],
                            'required' => ['code', 'value', 'basis'],
                        ],
                    ],
                    'variants' => [
                        'type' => 'array',
                        'description' => 'Les attributs « par variante », une entrée par variante et par attribut.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'variant_id' => ['type' => 'integer', 'description' => 'Identifiant de la variante.'],
                                'code' => ['type' => 'string', 'description' => "Le code de l'attribut."],
                                'value' => ['type' => 'string', 'description' => 'La valeur pour cette variante.'],
                            ],
                            'required' => ['variant_id', 'code', 'value'],
                        ],
                    ],
                ],
                'required' => ['values'],
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
     * What the product sheet establishes for the category's attributes.
     *
     * @param  list<string>  $perVariant  codes answered variant by variant
     * @param  list<string>  $skip  codes already answered, left as they are
     * @return array{values: array<string, string>, variants: array<int, array<string, string>>, assumed: list<string>}
     */
    public function fill(Product $product, OctopiaTemplate $template, array $perVariant = [], array $skip = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('No Anthropic API key is configured.');
        }

        $fields = $this->pending($template, $skip);

        if ($fields === []) {
            return ['values' => [], 'variants' => [], 'assumed' => []];
        }

        $client = new Client(apiKey: $this->apiKey);

        try {
            $message = $client->messages->create(
                model: 'claude-opus-5',
                maxTokens: self::MAX_TOKENS,
                system: self::SYSTEM_PROMPT,
                // Reading facts off a sheet is not a reasoning problem, and the
                // admin is waiting on the button: no thinking, moderate effort.
                thinking: ['type' => 'disabled'],
                outputConfig: ['effort' => 'medium'],
                messages: [['role' => 'user', 'content' => $this->brief($product, $template, $fields, $perVariant)]],
                tools: [self::tool()],
                toolChoice: ['type' => 'tool', 'name' => self::tool()['name']],
                workspaceID: $this->workspaceId ?: null,
            );
        } catch (APIStatusException $e) {
            // The API's own sentence, not merely its error type.
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
                return $this->normalize((array) $block->input, $fields, $perVariant, $product);
            }
        }

        throw new RuntimeException('Claude answered with nothing to read.');
    }

    /**
     * The attributes still to be answered: the category's own, less the ones
     * the seller already answered, which are neither asked again nor replaced.
     *
     * @param  list<string>  $skip
     * @return list<array<string, mixed>>
     */
    public function pending(OctopiaTemplate $template, array $skip = []): array
    {
        return array_values(array_filter(
            $template->attributeFields(),
            fn (array $field): bool => ! in_array((string) $field['code'], $skip, true),
        ));
    }

    /**
     * The product sheet and the attributes to answer, flattened.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $perVariant
     */
    private function brief(Product $product, OctopiaTemplate $template, array $fields, array $perVariant): string
    {
        $lines = ['Fiche produit de la boutique :', ''];
        $lines[] = 'Nom : '.$product->localizedName();

        if (filled($product->brandName())) {
            $lines[] = 'Marque : '.$product->brandName();
        }

        if ($product->category !== null) {
            $lines[] = 'Catégorie de la boutique : '.$product->category->localizedName();
        }

        if ($product->weight_grams) {
            $lines[] = 'Poids : '.$product->weight_grams.' g';
        }

        foreach (array_merge($product->characteristics ?? [], $product->filter_attributes ?? []) as $row) {
            if (filled($row['label'] ?? null) && filled($row['value'] ?? null)) {
                $lines[] = $row['label'].' : '.$row['value'];
            }
        }

        if (filled($product->meta_description)) {
            $lines[] = 'Résumé : '.trim($product->meta_description);
        }

        $description = trim($product->localizedDescriptionText());

        if ($description !== '') {
            $lines[] = '';
            $lines[] = 'Description de la fiche :';
            $lines[] = Str::limit($description, self::DESCRIPTION_LIMIT);
        }

        $variants = $perVariant === [] ? collect() : $product->variants->where('is_active', true);

        if ($variants->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Variantes :';

            foreach ($variants as $variant) {
                $lines[] = '- variante '.$variant->id.' : '.($variant->label() !== '' ? $variant->label() : 'sans libellé')
                    .($variant->sku ? ' (référence '.$variant->sku.')' : '');
            }
        }

        $lines[] = '';
        $lines[] = 'Catégorie Cdiscount : '.$template->name;
        $lines[] = 'Attributs à remplir, seulement si la fiche les établit :';

        foreach ($fields as $field) {
            $lines[] = '';
            $lines[] = '- code '.$field['code'].' : '.$field['label']
                .($field['required'] ? ' (obligatoire)' : '')
                .(in_array((string) $field['code'], $perVariant, true) ? ' (par variante)' : '');

            if (filled($field['constraint'] ?? null)) {
                $lines[] = '  Contrainte : '.$field['constraint'];
            }

            if (isset(self::ASSUMABLE[(string) $field['code']])) {
                $lines[] = '  Peut être supposé : '.self::ASSUMABLE[(string) $field['code']];
            }

            if (! empty($field['options'])) {
                $lines[] = '  Liste fermée, une option exactement : '.implode(' | ', array_slice($field['options'], 0, self::OPTIONS_LIMIT));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * What Claude answered, held to what the category allows.
     *
     * The answer comes from outside and is only as reliable as the model: a
     * code that is not the category's, a colour that is not on Octopia's list,
     * a size in the wrong unit would each be refused by Octopia days later.
     * So a value is kept only when it is one the category would take.
     *
     * A value is assumed when Claude said so, and only the attributes the shop
     * lets it assume may be: for any other, a value that is not on the sheet is
     * dropped. An attribute that may be assumed and comes back with no word on
     * where the value is from is taken as assumed, the cautious reading.
     *
     * @param  array<string, mixed>  $input
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $perVariant
     * @return array{values: array<string, string>, variants: array<int, array<string, string>>, assumed: list<string>}
     */
    private function normalize(array $input, array $fields, array $perVariant, Product $product): array
    {
        $byCode = collect($fields)->keyBy(fn (array $field): string => (string) $field['code']);
        $variantIds = $product->variants->where('is_active', true)->pluck('id')->all();

        $values = [];
        $assumed = [];

        foreach ((array) ($input['values'] ?? []) as $entry) {
            $code = (string) ($entry['code'] ?? '');
            $field = $byCode->get($code);
            $value = $field === null ? null : $this->clean($field, (string) ($entry['value'] ?? ''));

            if ($value === null) {
                continue;
            }

            $assumable = isset(self::ASSUMABLE[$code]);
            $basis = Str::lower(Str::ascii((string) ($entry['basis'] ?? '')));
            $isAssumed = $basis === 'suppose' || ($basis === '' && $assumable);

            // Not a value Claude may assume, and not one the sheet gave.
            if ($isAssumed && ! $assumable) {
                continue;
            }

            $values[$code] = $value;

            if ($isAssumed) {
                $assumed[] = $code;
            }
        }

        $variants = [];

        foreach ((array) ($input['variants'] ?? []) as $entry) {
            $id = (int) ($entry['variant_id'] ?? 0);
            $code = (string) ($entry['code'] ?? '');
            $field = $byCode->get($code);

            // Only the codes answered per variant, and only this product's variants.
            if ($field === null || ! in_array($code, $perVariant, true) || ! in_array($id, $variantIds, true)) {
                continue;
            }

            $value = $this->clean($field, (string) ($entry['value'] ?? ''));

            if ($value !== null) {
                $variants[$id][$code] = $value;
            }
        }

        return ['values' => $values, 'variants' => $variants, 'assumed' => $assumed];
    }

    /**
     * One value, as the attribute would take it, or null when it would not.
     *
     * @param  array<string, mixed>  $field
     */
    private function clean(array $field, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $multiple = str_starts_with((string) ($field['kind'] ?? ''), 'multi');
        $parts = $multiple ? array_filter(array_map('trim', explode(';', $value)), fn (string $part): bool => $part !== '') : [$value];
        $options = (array) ($field['options'] ?? []);

        if ($options !== []) {
            // Accents and case set aside to find the option, and the option's
            // own spelling is what is kept.
            $known = collect($options)->mapWithKeys(fn (string $option): array => [$this->key($option) => $option]);
            $parts = array_filter(array_map(fn (string $part): ?string => $known->get($this->key($part)), $parts));
        } elseif (str_contains((string) ($field['constraint'] ?? ''), 'Numérique')) {
            $parts = array_map(fn (string $part): string => str_replace(',', '.', str_replace(["\u{00a0}", ' '], '', $part)), $parts);
            $parts = array_filter($parts, 'is_numeric');
        }

        $parts = array_values(array_unique($parts));

        return $parts === [] ? null : Str::limit(implode(';', $multiple ? $parts : [$parts[0]]), 5000, '');
    }

    private function key(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
