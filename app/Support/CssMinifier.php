<?php

namespace App\Support;

/**
 * A conservative CSS minifier: strips comments, collapses whitespace and
 * drops the separators the grammar does not need. Quoted strings pass
 * through untouched, and nothing inside parentheses is reshaped — calc()
 * needs its spaces around operators.
 */
class CssMinifier
{
    public static function minify(string $css): string
    {
        $css = self::stripComments($css);

        // Quoted strings are copied verbatim; only the CSS between them is
        // compacted. (Comments went first: a French « l'accent » inside one
        // would otherwise read as an opening quote.)
        $parts = preg_split(
            '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/',
            $css,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        $out = '';

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $out .= $part;

                continue;
            }

            $part = preg_replace('/\s+/', ' ', $part);
            $part = preg_replace('/\s*([{};,])\s*/', '$1', $part);
            $part = preg_replace('/:\s+/', ':', $part);
            $part = str_replace(';}', '}', $part);

            $out .= $part;
        }

        return trim($out);
    }

    /**
     * Comments removed by a character walk rather than a regex, so a
     * quote inside a comment — or a slash inside a string — misleads
     * nothing.
     */
    private static function stripComments(string $css): string
    {
        $out = '';
        $length = strlen($css);
        $inString = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($inString !== null) {
                $out .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $out .= $css[++$i];
                } elseif ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = $char;
                $out .= $char;

                continue;
            }

            if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }
}
