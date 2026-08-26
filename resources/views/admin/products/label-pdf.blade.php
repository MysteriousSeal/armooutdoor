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

    The face is laid out as it reads (wide, short), then turned about its
    top-left corner so the turned box fills the sheet above the barcode.

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
            font-size: 14px;
            line-height: 1.35;
            color: #1a1a1a;
            background: #fff;
        }

        /*
            Reading face: 552 × 464, sitting with its top-left on the paper
            at the bottom of the text area. A quarter turn about that corner
            stands the box up, filling 18–482 × 18–570, clear of the barcode.
        */
        .face {
            position: absolute;
            left: 18px;
            top: 570px;
            width: 552px;
            height: 464px;
            transform: rotate(-90deg);
            transform-origin: 0 0;
        }

        .card {
            width: 552px;
            height: 464px;
            overflow: hidden;
            border: 2px solid #111;
            background: #fff;
        }

        table.sheet {
            width: 548px;
            border-collapse: collapse;
        }
        table.sheet td {
            vertical-align: top;
            padding: 0;
        }

        .header {
            height: 130px;
            color: #111;
            background: #fff;
            border-bottom: 3px solid #111;
        }
        .title {
            font-size: 30px;
            font-weight: bold;
            letter-spacing: 0.02em;
            line-height: 1.15;
        }
        .subtitle {
            margin-top: 8px;
            font-size: 15px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #111;
        }

        .pad { padding: 16px 20px 14px; }
        .header .pad { padding: 22px 20px 16px; }
        table.foot-row .pad {
            font-size: 14px;
            line-height: 1.35;
        }
        .foot-mention .pad {
            padding-top: 9px;
        }

        .cell { border-bottom: 1px solid #111; }
        .cell-meta { height: 88px; }
        .cell-info { height: 136px; }
        .cell-foot { height: 106px; }
        .cell-sku { width: 348px; }
        .cell-side { width: 200px; border-left: 1px solid #111; }
        .cell-last { border-bottom: none; }

        .field-label {
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .sku {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 0.02em;
        }
        .batch .value {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.04em;
        }

        .importer { line-height: 1.45; }
        .value { font-size: 15px; }

        table.foot-row {
            width: 548px;
            border-collapse: collapse;
        }
        table.foot-row td {
            vertical-align: top;
            padding: 0;
            font-size: 0;
            line-height: 0;
        }
        .foot-comp { width: 250px; }
        .foot-mention { width: 298px; }
        .mention .value {
            font-size: 15px;
            font-weight: bold;
            line-height: 1.35;
        }

        /* Flat on the paper, upright against the label: the barcode sits along
           the edge the reading face ends at. */
        .barcode-strip {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 120px;
            padding-top: 10px;
            text-align: center;
        }
        .barcode { margin: 0 auto; border-collapse: collapse; }
        .barcode td { height: 80px; padding: 0; font-size: 0; line-height: 0; }
        .bar { background: #000; }
        .space { background: #fff; }
        .digits {
            margin-top: 6px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.28em;
        }
    </style>
</head>
<body>
    <div class="face">
        <div class="card">
            <table class="sheet">
                <tr>
                    <td class="header" colspan="2">
                        <div class="pad">
                            @if (filled($title))
                                <div class="title">{{ $title }}</div>
                            @endif
                            @if (filled($subtitle))
                                <div class="subtitle">{{ $subtitle }}</div>
                            @endif
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="cell cell-meta cell-sku">
                        <div class="pad">
                            <div class="field-label">SKU</div>
                            <div class="sku">{{ $sku }}</div>
                        </div>
                    </td>
                    <td class="cell cell-meta cell-side batch">
                        <div class="pad">
                            <div class="field-label">Batch</div>
                            <div class="value">{{ $batchDate }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="cell cell-info cell-sku importer">
                        <div class="pad">
                            <div class="field-label">Importer</div>
                            <div class="value">
                                SwiftShelf<br>
                                22 rue Anita Conti<br>
                                44300 Nantes, FR
                            </div>
                        </div>
                    </td>
                    <td class="cell cell-info cell-side">
                        <div class="pad">
                            <div class="field-label">Contact</div>
                            <div class="value">hello@swiftshelf.fr</div>
                            <div class="field-label" style="margin-top: 14px;">Origin</div>
                            <div class="value">Made in PRC</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="cell cell-foot cell-last" colspan="2">
                        @if (filled($composition) && filled($mention))
                            <table class="foot-row">
                                <tr>
                                    <td class="foot-comp" valign="top"><div class="pad">
                                            <div class="field-label">Composition</div>
                                            <div class="value">{{ $composition }}</div>
                                        </div></td>
                                    <td class="foot-mention" valign="top"><div class="pad mention">
                                            <div class="value">{{ $mention }}</div>
                                        </div></td>
                                </tr>
                            </table>
                        @else
                            <div class="pad">
                                @if (filled($composition))
                                    <div class="field-label">Composition</div>
                                    <div class="value">{{ $composition }}</div>
                                @endif
                                @if (filled($mention))
                                    <div class="mention">
                                        <div class="value">{{ $mention }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
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
