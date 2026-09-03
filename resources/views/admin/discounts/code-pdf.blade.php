<!DOCTYPE html>
{{--
    Une carte de 70 × 50 mm, paysage, ne portant que le code — rien
    d'autre, à la demande — et dessous, ce que le code vaut et, s'il
    expire, sa date limite : offrir un code déjà mort est pire que ne
    rien offrir. La taille de police descend avec la longueur
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

        .deadline {
            margin-top: 5pt;
            font-size: 10pt;
            letter-spacing: 0.5pt;
            color: #555555;
        }

        .amount {
            margin-top: 6pt;
            font-size: 13pt;
            font-weight: bold;
            color: #1a1a1a;
        }

        .date {
            margin-top: 2pt;
            font-size: 11pt;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="card">
                <span class="code">{{ $code }}</span>
                @if (($amount ?? null) !== null)
                    <div class="amount">{{ $amount }}</div>
                @endif
                @if (($endsAt ?? null) !== null)
                    <div class="deadline">Valable jusqu'au</div>
                    <div class="date">{{ $endsAt->format('d/m/Y') }}</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
