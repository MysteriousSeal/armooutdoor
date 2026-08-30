<!DOCTYPE html>
{{--
    Une carte de 70 × 50 mm, paysage, ne portant que le code — rien
    d'autre, à la demande. La taille de police descend avec la longueur
    du code pour qu'il tienne sur une seule ligne, et l'interlettrage
    suit le même mouvement : large sur un code court, resserré sur un
    long.
--}}
@php
    // La carte fait 198 pt de large (70 mm) ; on en garde 180 pour le
    // code. Une capitale de DejaVu Sans bold avance d'environ 0,8 em au
    // pire (le W), et l'interlettrage ajoute 0,12 em par lettre : chaque
    // caractère occupe donc ~0,92 em. La taille s'en déduit au lieu
    // d'être devinée par paliers — c'est ce qui rognait les codes.
    // Plancher à 4 pt : un code de 40 caractères (le maximum admis) tient
    // encore — petit, mais entier, quand un code rogné ne sert à rien.
    $length = max(1, mb_strlen($code));
    $size = (int) min(30, max(4, floor(180 / ($length * 0.92))));
    $spacing = round($size * 0.12, 1);
@endphp
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
        }

        .card {
            width: 100%;
            height: 141pt; /* 50 mm */
            text-align: center;
            vertical-align: middle;
        }

        .code {
            font-size: {{ $size }}pt;
            font-weight: bold;
            letter-spacing: {{ $spacing }}pt;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="card">
                <span class="code">{{ $code }}</span>
            </td>
        </tr>
    </table>
</body>
</html>
