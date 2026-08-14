<?php

namespace App\Support;

class SimplePdf
{
    /**
     * Build a minimal, valid single-page PDF containing the given line of text.
     * Placeholder generator — no PDF library dependency required.
     */
    public static function withText(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 24 Tf 72 700 Td ({$escaped}) Tj ET";

        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            2 => "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            3 => "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            4 => "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            5 => "5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $body;
        }

        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }
}
