{{--
    The frame every accounting journal is printed in.

    One page per month, landscape, written in French: the sheet is filed with
    the accounts, not read on the admin screen. Both journals wear it, so the
    two file together and an edit to the letterhead or the signature block is
    made once.

    A journal fills three slots:
      - `title`  the document's name, "Journal des ventes" and the like
      - `table`  its own columns and totals
      - `note`   the sentence under the table explaining its figures

    Every style is inline here. The PDF renderer has no access to the site's
    stylesheets.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>@yield('title') {{ \App\Support\AccountingPeriods::key($period) }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11.5px;
            line-height: 1.45;
            color: #2c2c2c;
            padding: 48px 56px;
        }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .header td { vertical-align: top; }
        .company-info { font-size: 9.5px; line-height: 1.4; color: #6b6b6b; }
        .company-info .name { margin-bottom: 2px; font-size: 11px; font-weight: bold; color: #2c2c2c; }
        table.company-cols { border-collapse: collapse; }
        table.company-cols td { vertical-align: top; }
        table.company-cols td + td { padding-left: 16px; }

        .title-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .title-row td { vertical-align: bottom; }
        .title {
            font-size: 20.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #2c2c2c;
        }
        .title-meta { text-align: right; font-size: 10.5px; color: #6b6b6b; }

        /* The three boxes under the title: period, entry count, print date. */
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.meta td {
            width: 33.33%;
            padding: 8px 10px;
            background: #f7f6f4;
            border: 1px solid #e8e6e3;
        }
        table.meta td + td { border-left: 0; }
        table.meta .label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        table.meta .value { padding-top: 3px; font-size: 11.5px; color: #2c2c2c; }

        /* The heading above a total, in small type: it recalls the column
           without weighing more than the figure. */
        .foot-label {
            display: block;
            margin-bottom: 2px;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b7e74;
        }

        .notes-body { margin-bottom: 12px; font-size: 9.5px; line-height: 1.5; color: #6b6b6b; }

        /* The signature, at the foot of the page. A frame rather than three
           bare rules: a missing signature has to show at a glance on a filed
           sheet, without reading the labels. */
        table.signature { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.signature td {
            padding: 9px 12px 10px;
            border: 1px solid #e8e6e3;
            background: #f7f6f4;
            vertical-align: top;
        }
        table.signature td.sign-name { width: 34%; }
        table.signature td.sign-date { width: 22%; }
        table.signature td + td { border-left: 0; }
        .sign-label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8b7e74;
        }
        .sign-scope { margin-bottom: 7px; font-size: 10.5px; color: #2c2c2c; }
        /* Tall enough for a real handwritten signature, not a paraph. */
        .sign-rule { height: 46px; }
        .sign-rule-short { height: 22px; }

        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e8e6e3;
            text-align: center;
            font-size: 9px;
            color: #6b6b6b;
        }
        {{-- Each journal adds the widths of its own columns. --}}
        @stack('styles')
    </style>
</head>
<body>
    {{-- The journal belongs to the company, not to the shop sign: SwiftShelf
         keeps the accounts, ArmoOutdoor is only the shop. --}}
    {{-- Letterhead: the company, top left, where a letterhead belongs. --}}
    <table class="header">
        <tr>
            <td class="company-info">
                <div class="name">{{ $company->value('company_name') }}</div>
                <table class="company-cols">
                    <tr>
                        <td>
                            @foreach ($company->addressLines() as $line)
                                {{ $line }}<br>
                            @endforeach
                            SIRET {{ $company->value('siret') }}
                        </td>
                        <td>
                            {{ $company->formattedPhone() }}<br>
                            {{-- The company address, not the shop's: the journal is
                                 a SwiftShelf document, and the shop contact can
                                 change in the settings without the accounting
                                 book following. --}}
                            hello@swiftshelf.fr
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="title-row">
        <tr>
            <td><div class="title">@yield('title')</div></td>
            <td class="title-meta">{{ \App\Support\AccountingPeriods::labelFr($period) }}</td>
        </tr>
    </table>

    {{-- The period it covers, how many lines it holds, and the day it was
         printed: enough to tell two copies of one month apart. --}}
    <table class="meta">
        <tr>
            <td>
                <div class="label">Période</div>
                <div class="value">{{ \App\Support\AccountingPeriods::dateFr($period, false) }} au {{ \App\Support\AccountingPeriods::dateFr($period->endOfMonth()) }}</div>
            </td>
            <td>
                <div class="label">Écritures</div>
                <div class="value">{{ $rows->count() }}</div>
            </td>
            <td>
                <div class="label">Édité le</div>
                <div class="value">{{ \App\Support\AccountingPeriods::dateFr(\Carbon\CarbonImmutable::now()) }}</div>
            </td>
        </tr>
    </table>

    @yield('table')

    <div class="notes-body">@yield('note')</div>

    <table class="signature">
        <tr>
            <td colspan="3" style="border-bottom: 0; background: #fff; padding-bottom: 2px;">
                <div class="sign-label">Signature</div>
                <div class="sign-scope">Pour {{ $company->value('company_name') }}</div>
            </td>
        </tr>
        <tr>
            <td class="sign-name">
                <div class="sign-label">Nom</div>
                <div class="sign-rule-short"></div>
            </td>
            <td class="sign-date">
                <div class="sign-label">Date</div>
                <div class="sign-rule-short"></div>
            </td>
            <td>
                <div class="sign-label">Signature</div>
                <div class="sign-rule"></div>
            </td>
        </tr>
    </table>

    <div class="footer">
        @yield('title') — {{ \App\Support\AccountingPeriods::labelFr($period) }}
    </div>
</body>
</html>
