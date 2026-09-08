<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Util;
use App\Models\Product;
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

    /** Vinted trims the title around here on a phone. */
    private const TITLE_LIMIT = 60;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Tu rédiges des annonces Vinted pour une petite boutique française
        d'équipement de tir sportif, chasse, airsoft et plein air. Les
        articles sont neufs, jamais utilisés, vendus avec facture.

        Écris comme un vendeur particulier soigneux écrit vraiment : phrases
        courtes, ton direct et chaleureux, aucun jargon marketing. Pas de
        superlatifs ("incroyable", "exceptionnel"), pas de majuscules
        d'insistance, pas de hashtags, pas de listes à puces, pas de gras ni
        de markdown — Vinted n'affiche aucune mise en forme.

        Le titre : 60 caractères maximum, le nom de l'article d'abord, puis la
        marque, la taille ou le calibre s'ils existent. Pas de prix, pas de
        "NEUF" en capitales.

        La description : 4 à 6 lignes courtes séparées par des retours à la
        ligne. Dans l'ordre : ce que c'est et à quoi ça sert, les
        caractéristiques concrètes (matière, taille, calibre, contenance),
        l'état neuf, puis une dernière ligne sur l'envoi rapide et soigné. Au
        plus un emoji, et seulement s'il tombe juste.

        Vinted modère les annonces automatiquement, et le vocabulaire de
        l'armement fait retirer une annonce avant même qu'un humain la lise.
        Décris donc l'accessoire, jamais ce sur quoi il se monte : parle de
        loisir, de sport de précision, de plein air, de nature. Évite « arme »,
        « fusil », « carabine », « pistolet », « munition », « cartouche »,
        « projectile », « balle », « tactique », « militaire », « combat »,
        « tuer », « abattre », et tout terme de violence ou de danger. Si un
        mot de la fiche produit tombe dans cette liste, remplace-le par le
        terme neutre le plus proche ou n'en parle pas. Aucune mention de
        législation, d'âge légal ni de catégorie réglementaire.

        N'invente rien : n'écris que ce que la fiche produit donne.

        Réponds uniquement par un objet JSON, sans texte autour et sans bloc
        de code, de la forme : {"title": "...", "description": "..."}
        PROMPT;

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
     * @return array{title: string, description: string}
     */
    public function write(Product $product): array
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
                messages: [['role' => 'user', 'content' => $this->brief($product)]],
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

        return $this->parse($this->text($message->content));
    }

    /**
     * The product sheet, flattened. Only what a listing can use: the price is
     * in there because it tells Claude what range of article this is, not so
     * that it writes it down.
     */
    private function brief(Product $product): string
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

        $lines[] = '';
        $lines[] = 'Rédige le titre et la description Vinted de cet article.';

        return implode("\n", $lines);
    }

    /**
     * The answer's text. Thinking is off, so a text block is expected first —
     * the loop holds all the same, since a content shape is not a promise.
     *
     * @param  array<int, object>  $content
     */
    private function text(array $content): string
    {
        foreach ($content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        throw new RuntimeException('Claude answered with no text.');
    }

    /**
     * @return array{title: string, description: string}
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

        if (! is_array($decoded) || ! isset($decoded['title'], $decoded['description'])) {
            throw new RuntimeException('Claude answered in an unexpected shape.');
        }

        return [
            // The limit is Vinted's own, and a title cut here is better than
            // one cut by the form after being pasted.
            'title' => Str::limit(trim((string) $decoded['title']), self::TITLE_LIMIT, ''),
            'description' => trim((string) $decoded['description']),
        ];
    }
}
