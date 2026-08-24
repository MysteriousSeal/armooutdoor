<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote',
        'span', 'div', 'pre', 'code',
    ];

    /** @var list<string> */
    private const ALLOWED_ATTRIBUTES = [
        'href', 'title', 'target', 'rel', 'class',
    ];

    /**
     * Ce qu'un article peut porter en plus, quand on le lui autorise.
     *
     * Les fiches produit passent par le même nettoyeur et n'ont aucun besoin
     * d'images dans leur corps de texte : la permission est demandée, jamais
     * acquise. `figcaption` accompagne `figure` — Quill entoure une image
     * légendée des deux, et sans les deux la légende ressort en texte nu.
     *
     * @var list<string>
     */
    private const IMAGE_TAGS = ['img', 'figure', 'figcaption'];

    /** @var list<string> */
    private const IMAGE_ATTRIBUTES = ['src', 'alt', 'width', 'height', 'loading'];

    /**
     * @param  bool  $allowImages  Autorise `img`/`figure` avec une source
     *                             servie par la boutique elle-même.
     */
    public static function clean(?string $html, bool $allowImages = false): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '' || self::isBlank($html, $allowImages)) {
            return null;
        }

        // Quill paste often inserts &nbsp; between words, which prevents wrapping.
        $html = str_replace(["\xc2\xa0", '&nbsp;', '&#160;', '&#xA0;'], ' ', $html);
        $html = preg_replace('/ {2,}/', ' ', $html) ?? $html;

        $document = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"><div id="sanitize-root">'.$html.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $root = $document->getElementById('sanitize-root');

        if (! $root) {
            return null;
        }

        self::sanitizeNode($root, $allowImages);

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        $clean = trim($clean);

        return self::isBlank($clean, $allowImages) ? null : $clean;
    }

    public static function forDisplay(?string $html): string
    {
        $html = self::clean($html) ?? '';

        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<a\b[^>]*>\s*</a>#iu', '', $html) ?? $html;
        $html = preg_replace('#<(span|div)\b[^>]*>\s*</\1>#iu', '', $html) ?? $html;
        $html = preg_replace('#(</strong>)\s+#iu', '$1 ', $html) ?? $html;
        $html = preg_replace('#\s+(<br\s*/?>)#iu', '$1', $html) ?? $html;
        $html = preg_replace('#(<br\s*/?>\s*){2,}#iu', '<br>', $html) ?? $html;

        return trim($html);
    }

    public static function toPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Block boundaries become spaces first: strip_tags alone runs the end
        // of one paragraph into the start of the next, which is how meta
        // descriptions ended up reading "…et chargeur.Le DLV36 reprend…".
        $spaced = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|ul|ol|h[1-6]|tr|td|th|blockquote|section|article)\s*>/i', ' ', $html) ?? $html;

        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function isEmpty(?string $html): bool
    {
        return self::toPlainText($html) === '';
    }

    /**
     * Vide au sens de ce qu'on est en train de nettoyer.
     *
     * `isEmpty()` ne regarde que le texte : un article composé d'une seule
     * image n'a rien à dire au sens des caractères, et se faisait jeter avant
     * même d'être examiné. Quand les images comptent comme du contenu, leur
     * présence suffit.
     */
    private static function isBlank(?string $html, bool $allowImages): bool
    {
        if ($allowImages && $html !== null && preg_match('/<img[\s>]/i', $html)) {
            return false;
        }

        return self::isEmpty($html);
    }

    private static function sanitizeNode(DOMNode $node, bool $allowImages = false): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'], true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $allowed = self::ALLOWED_TAGS;

            if ($allowImages) {
                $allowed = array_merge($allowed, self::IMAGE_TAGS);
            }

            if (! in_array($tag, $allowed, true)) {
                self::sanitizeNode($child, $allowImages);
                self::unwrapNode($child);

                continue;
            }

            // Une image dont la source est refusée part entièrement. Retirer
            // le seul attribut laisserait un `<img>` nu, c'est-à-dire une
            // icône cassée — contrairement à un `<a>` vidé de son href, qui
            // garde au moins son texte.
            if ($tag === 'img' && ! self::hasSameOriginSource($child)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            self::sanitizeAttributes($child, $tag, $allowImages);
            self::sanitizeNode($child, $allowImages);
        }
    }

    /**
     * Une image ne peut venir que de la boutique.
     *
     * Deux formes acceptées, et deux seulement : un chemin absolu depuis la
     * racine du site, ou une URL http(s) dont l'hôte est le nôtre. Tout le
     * reste part — `data:`, `javascript:`, et n'importe quel autre domaine.
     *
     * Le piège est `//exemple.com/x.jpg` : il commence bien par une barre
     * oblique, mais c'est une URL sans protocole qui charge ailleurs. D'où le
     * refus explicite du double slash avant tout le reste.
     */
    private static function hasSameOriginSource(DOMElement $image): bool
    {
        $src = trim($image->getAttribute('src'));

        if ($src === '' || str_starts_with($src, '//')) {
            return false;
        }

        if (str_starts_with($src, '/')) {
            return true;
        }

        if (! preg_match('#^https?://#i', $src)) {
            return false;
        }

        $host = parse_url($src, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host !== null
            && $appHost !== null
            && strcasecmp($host, $appHost) === 0;
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag, bool $allowImages = false): void
    {
        $toRemove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);

            $permitted = self::ALLOWED_ATTRIBUTES;

            // Les attributs d'image ne valent que sur une image : les ajouter
            // à la liste globale les laisserait traîner sur un `<span>`.
            if ($allowImages && $tag === 'img') {
                $permitted = array_merge($permitted, self::IMAGE_ATTRIBUTES);
            }

            if (! in_array($name, $permitted, true)) {
                $toRemove[] = $attribute->name;

                continue;
            }

            if ($name === 'href') {
                $href = trim($attribute->value);

                if ($href === '' || preg_match('/^\s*javascript:/i', $href) || preg_match('/^\s*data:/i', $href)) {
                    $toRemove[] = $attribute->name;

                    continue;
                }

                if (! preg_match('#^(https?://|/|\#|mailto:)#i', $href)) {
                    $toRemove[] = $attribute->name;
                }
            }

            if ($name === 'class' && ! str_starts_with($attribute->value, 'ql-')) {
                $toRemove[] = $attribute->name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'img' && ! $element->hasAttribute('alt')) {
            $element->setAttribute('alt', '');
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');

            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('target', '_blank');
            }
        }
    }

    private static function unwrapNode(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
