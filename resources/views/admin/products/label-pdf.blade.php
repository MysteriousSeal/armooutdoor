{{--
    A product label, one article per sheet.

    The sheet is portrait — 500 × 700 CSS pixels, given to the renderer as
    375 × 525 points — and the label is printed across it on its side, the way
    a label for a package wider than it is tall is laid out.

    So the reading face is turned a quarter turn and the barcode is drawn flat:
    on the paper the barcode runs across the foot, and against the label's own
    reading direction it stands upright beside the text. One rotation, on the
    text; nesting a second inside it is not something the renderer is reliable
    about.

    The batch date is the day the label was printed rather than anything
    stored: a batch is what goes out wearing these labels.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label {{ $sku }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            position: relative;
            width: 500px;
            height: 700px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.45;
            color: #111;
        }

        /* The reading face, laid out as it reads and then turned. Rotation is
           about the centre, so the box is placed where its centre should end
           up and the turn keeps it there. */
        .face {
            position: absolute;
            left: 18px;
            top: 62px;
            width: 470px;
            height: 456px;
            transform: rotate(-90deg);
        }

        /* The name first: a label is read as a product before it is read as a
           reference. Both are printed only when there is something to print. */
        .title {
            font-size: 30px;
            font-weight: bold;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }
        .subtitle {
            margin-top: 3px;
            font-size: 16px;
            color: #444;
        }

        /* The reference, smaller than the name but still the thing the label
           is looked up by on a shelf. */
        .sku {
            font-size: 21px;
            font-weight: bold;
            letter-spacing: 0.02em;
            word-break: break-all;
        }
        .field-label {
            margin-bottom: 2px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .rule { height: 1px; margin: 16px 0; background: #d8d4cf; }

        .block { margin-bottom: 13px; }
        /* A warning reads as one: set apart, and heavier than the fixed text. */
        .mention {
            padding: 7px 9px;
            font-weight: bold;
            background: #f4f2ef;
            border-left: 3px solid #8b7e74;
        }
        .importer { line-height: 1.5; }

        /* The batch closes the face, where a date is looked for, and stands out
           from the fixed text above it. */
        .batch {
            margin-top: 4px;
            padding-top: 11px;
            border-top: 1px solid #d8d4cf;
        }
        .batch .value { font-size: 18px; font-weight: bold; }

        /* Flat on the paper, upright against the label: the barcode sits along
           the edge the reading face ends at. */
        .barcode-strip {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 34px;
            text-align: center;
        }
        .barcode { margin: 0 auto; border-collapse: collapse; }
        .barcode td { height: 104px; padding: 0; font-size: 0; line-height: 0; }
        .bar { background: #000; }
        .space { background: #fff; }
        .digits {
            margin-top: 7px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.3em;
        }
    </style>
</head>
<body>
    <div class="face">
        @if (filled($title))
            <div class="title">{{ $title }}</div>
        @endif
        @if (filled($subtitle))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif

        @if (filled($title) || filled($subtitle))
            <div class="rule"></div>
        @endif

        <div class="field-label">SKU</div>
        <div class="sku">{{ $sku }}</div>

        <div class="rule"></div>

        <div class="block importer">
            <div class="field-label">Importer</div>
            <div class="value">
                SwiftShelf<br>
                22 rue Anita Conti<br>
                44300 Nantes, FR
            </div>
        </div>

        <div class="block">
            <div class="field-label">Contact</div>
            <div class="value">hello@swiftshelf.fr</div>
        </div>

        <div class="block">
            <div class="field-label">Origin</div>
            <div class="value">Made in PRC</div>
        </div>

        {{-- Optional, and silent when empty: a heading with nothing under it
             would say less than no heading at all. --}}
        @if (filled($composition))
            <div class="block">
                <div class="field-label">Composition</div>
                <div class="value">{{ $composition }}</div>
            </div>
        @endif

        @if (filled($mention))
            <div class="block mention">
                <div class="value">{{ $mention }}</div>
            </div>
        @endif

        <div class="batch">
            <div class="field-label">Batch</div>
            <div class="value">{{ $batchDate }}</div>
        </div>
    </div>

    <div class="barcode-strip">
        @if ($modules)
            {{-- One cell per module, 3px wide: 95 modules make 285px. --}}
            <table class="barcode">
                <tr>
                    @foreach (str_split($modules) as $module)
                        <td class="{{ $module === '1' ? 'bar' : 'space' }}" width="3"></td>
                    @endforeach
                </tr>
            </table>
        @endif
        <div class="digits">{{ $gtin }}</div>
    </div>
</body>
</html>
