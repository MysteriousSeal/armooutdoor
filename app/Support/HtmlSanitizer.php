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

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '' || self::isEmpty($html)) {
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

        self::sanitizeNode($root);

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        $clean = trim($clean);

        return self::isEmpty($clean) ? null : $clean;
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

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function isEmpty(?string $html): bool
    {
        return self::toPlainText($html) === '';
    }

    private static function sanitizeNode(DOMNode $node): void
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

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeNode($child);
                self::unwrapNode($child);

                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::sanitizeNode($child);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $toRemove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
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
