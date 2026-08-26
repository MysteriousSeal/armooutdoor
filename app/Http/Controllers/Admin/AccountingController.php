<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountingEntryRequest;
use App\Models\AccountingEntry;
use App\Models\AccountingJournalDownload;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Support\AccountingPeriods;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The accounting section: what came in, what went out.
 *
 * Two halves, sales and purchases, each showing a list of months and then one
 * page per month, and each able to print a month as a journal for the
 * accounting book.
 *
 * The two differ in where their lines come from. A month of sales mixes the
 * shop's own orders with entries typed by hand; a month of purchases is
 * hand-written throughout, since a purchase order carries its own VAT rate
 * and its own receipt dates. Either way the journal PDF prints exactly the
 * rows the screen shows, which is what keeps the paper and the page in step.
 */
class AccountingController extends Controller
{
    /** The list of sales months. */
    public function sales(): View
    {
        return view('admin.accounting.index', $this->listData('sales'));
    }

    /** The list of purchase months. */
    public function purchases(): View
    {
        return view('admin.accounting.index', $this->listData('purchases'));
    }

    /** One month of sales: the table of lines and its totals. */
    public function salesMonth(string $month): View
    {
        return view('admin.accounting.month', $this->monthData('sales', $month));
    }

    /** One month of purchases: what was paid out, and to whom. */
    public function purchasesMonth(string $month): View
    {
        return view('admin.accounting.month', $this->monthData('purchases', $month));
    }

    /**
     * What a list of months needs: the months themselves and their counts.
     *
     * @return array<string, mixed>
     */
    private function listData(string $section): array
    {
        return [
            'section' => $section,
            'title' => $this->title($section),
            'lede' => $this->lede($section),
            // Grouped by year: past twelve months, one long column of months
            // no longer says where a financial year begins.
            'years' => AccountingPeriods::months()->groupBy(fn ($month): string => $month->format('Y')),
            'counts' => $this->countsByMonth($section),
            // Which months have already been filed, so a year-end sweep shows
            // at a glance what is left to take out.
            'downloads' => $downloads = AccountingJournalDownload::latestByMonth($section),
            'stale' => $this->staleMonths($section, $downloads),
        ];
    }

    /**
     * How many entries each month holds, in two queries.
     *
     * One query per month shown would be twelve for a list that prints a
     * number. Hand-written entries count like orders: the list says how many
     * lines there are to read in the month.
     *
     * @return Collection<string, int>
     */
    private function countsByMonth(string $section): Collection
    {
        // Purchases are hand-written only; sales also count the shop's orders.
        $orders = $section === 'sales'
            ? $this->countByMonth($this->soldQuery(), 'created_at')
            : collect();

        $entries = $this->countByMonth(
            AccountingEntry::query()->section($section),
            'entered_on',
        );

        return $orders->toBase()
            ->keys()
            ->merge($entries->keys())
            ->unique()
            ->mapWithKeys(fn (string $month): array => [
                $month => (int) ($orders[$month] ?? 0) + (int) ($entries[$month] ?? 0),
            ]);
    }

    /**
     * Counts rows per calendar month, keyed "2026-03".
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  string  $column  The date column to group on.
     * @return Collection<string, int>
     */
    private function countByMonth(Builder $query, string $column): Collection
    {
        // SQLite in development, MySQL in production: the two do not cut a
        // date apart the same way.
        $month = $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";

        return $query->selectRaw($month.' as month, count(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');
    }

    /**
     * What one month's page needs.
     *
     * Both sections get their lines, the state of the last copy taken out and
     * whether one can be taken at all; only sales carry the lists their entry
     * form offers, purchases writing their kind out instead.
     *
     * @return array<string, mixed>
     */
    private function monthData(string $section, string $month): array
    {
        // An unparseable or out-of-range month has no page at all.
        $period = AccountingPeriods::parse($month);

        abort_if($period === null, 404);

        $data = [
            'section' => $section,
            'title' => $this->title($section),
            'period' => $period,
        ];

        $rows = $section === 'purchases'
            ? $this->purchaseRowsOf($period)
            : $this->rowsOf($section, $period);

        $lastDownload = AccountingJournalDownload::latestFor($section, $month);

        $data['rows'] = $rows;
        $data['paymentMethods'] = AccountingEntry::PAYMENT_METHODS;
        $data['lastDownload'] = $lastDownload;
        // Whether the filed copy still says what the month says now.
        $data['stale'] = $lastDownload?->isStaleAgainst($this->fingerprintOf($section, $rows)) ?? false;
        // A month with no line has nothing to print, so its button is off for
        // the same reason a running month's is.
        $data['downloadable'] = AccountingPeriods::isClosed($period) && $rows->isNotEmpty();

        if ($section === 'sales') {
            // Only sales pick their kind from a list; a purchase writes it out.
            $data['entryTypes'] = AccountingEntry::TYPES;
        }

        return $data;
    }

    /**
     * The month's lines: the orders and the hand-written entries.
     *
     * Sorted by date, among one another — an entry written by hand is not an
     * appendix to the table, it is a line of it.
     *
     * Both sources are flattened into the same shape so the table, the totals
     * and the PDF read one list and never ask where a line came from. Each row
     * carries:
     *
     * - `kind`, `order`, `entry`: which source it came from, and the model
     *   behind it when a link or an edit button is needed.
     * - the printed columns: `invoice`, `client`, `channel`, `type`,
     *   `total_cents`, `fees_cents`, `payment`, `remark`.
     * - `type_fr`, `payment_fr`: the same two labels in French, for the PDF.
     * - `counts`: whether the line joins the totals. False for a refund.
     * - `refunded`: whether to strike it through.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsOf(string $section, CarbonImmutable $period): Collection
    {
        // The shop's own orders, as table rows.
        $orders = $this->salesOf($period)->map(fn (Order $order): array => [
            'kind' => 'order',
            'date' => $order->created_at->startOfDay(),
            'order' => $order,
            'entry' => null,
            'invoice' => 'INV-'.$order->number,
            'client' => $order->user?->name ?? '—',
            'channel' => $order->marketplace_name ?: ($order->marketplace?->name ?? 'Direct'),
            'type' => 'Stock sale',
            // The accounting documents are in French; the screen stays in
            // English like the rest of the admin.
            'type_fr' => AccountingEntry::TYPES_FR['stock_sale'],
            'total_cents' => $order->total_cents,
            'fees_cents' => ($order->marketplace_commission_cents ?? 0) + ($order->payment_fee_cents ?? 0),
            'payment' => 'Bank wire',
            'payment_fr' => AccountingEntry::PAYMENT_METHODS_FR['bank_wire'],
            'remark' => $order->number,
            'counts' => $order->status !== 'refunded',
            'refunded' => $order->status === 'refunded',
        ]);

        // The entries typed by hand for this section and this month.
        $entries = AccountingEntry::query()
            ->section($section)
            ->whereBetween('entered_on', [$period, $period->endOfMonth()])
            ->orderBy('entered_on')
            ->orderBy('id')
            ->get()
            ->map(fn (AccountingEntry $entry): array => [
                'kind' => 'entry',
                'date' => $entry->entered_on,
                'order' => null,
                'entry' => $entry,
                'invoice' => $entry->invoice_number ?: '—',
                'client' => $entry->client ?: '—',
                'channel' => $entry->channel ?: 'Direct',
                'type' => $entry->typeLabel(),
                'type_fr' => $entry->typeLabelFr(),
                'total_cents' => $entry->total_cents,
                'fees_cents' => $entry->fees_cents,
                'payment' => $entry->paymentLabel(),
                'payment_fr' => $entry->paymentLabelFr(),
                'remark' => $entry->remark ?: '',
                'counts' => true,
                'refunded' => false,
            ]);

        // `toBase()` first: merging arrays into an Eloquent collection makes
        // it try to read a key off each one. The invoice number breaks ties so
        // two lines of the same day keep a stable order between page loads.
        return $orders->toBase()
            ->merge($entries->toBase())
            ->sortBy([['date', 'asc'], ['invoice', 'asc']])
            ->values();
    }

    /**
     * The month's journal as a PDF, for the accounting book.
     *
     * The same lines and the same totals as the page: a document that said
     * something else would be worth nothing.
     */
    public function salesPdf(Request $request, string $month): Response
    {
        $period = $this->journalPeriod('sales', $month, $rows);

        return $this->journal(
            $request,
            'sales',
            $period,
            'admin.accounting.sales-pdf',
            $this->journalData($period),
            $this->fingerprint($rows),
            'ventes',
        );
    }

    /**
     * The filed months whose figures have moved since.
     *
     * Only months already downloaded are rebuilt: a month nobody has taken a
     * copy of cannot be out of date, and rebuilding every month of the list
     * would read the whole year to answer a question about a handful.
     *
     * @param  Collection<string, AccountingJournalDownload>  $downloads
     * @return Collection<string, bool>
     */
    private function staleMonths(string $section, Collection $downloads): Collection
    {
        return $downloads->mapWithKeys(function (AccountingJournalDownload $download, string $month) use ($section): array {
            $period = AccountingPeriods::parse($month);

            if ($period === null) {
                return [$month => false];
            }

            $rows = $section === 'purchases'
                ? $this->purchaseRowsOf($period)
                : $this->rowsOf('sales', $period);

            return [$month => $download->isStaleAgainst($this->fingerprintOf($section, $rows))];
        });
    }

    /**
     * The month's purchases, oldest first.
     *
     * Hand-written only for now: a purchase order carries its own VAT rate and
     * its own receipt dates, and folding those in raises a question this table
     * does not answer yet — whether a line belongs to the month it was ordered
     * or the month it arrived.
     *
     * @return Collection<int, AccountingEntry>
     */
    private function purchaseRowsOf(CarbonImmutable $period): Collection
    {
        return AccountingEntry::query()
            ->section('purchases')
            ->whereBetween('entered_on', [$period, $period->endOfMonth()])
            ->orderBy('entered_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * A signature of the lines a journal prints.
     *
     * Only the printed values go in: touching a tracking number or an internal
     * note leaves it unchanged, while a total, a fee, a status or a deleted
     * entry moves it. A date could not say as much — a removed line touches
     * nothing at all.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function fingerprint(Collection $rows): string
    {
        return hash('sha256', $rows->map(fn (array $row): string => implode('|', [
            $row['date']->format('Y-m-d'),
            $row['invoice'],
            $row['client'],
            $row['channel'],
            $row['type'],
            $row['total_cents'],
            $row['fees_cents'],
            $row['payment'],
            $row['remark'],
            $row['counts'] ? '1' : '0',
            $row['refunded'] ? '1' : '0',
        ]))->join("\n"));
    }

    /**
     * The month's purchases as a PDF, for the accounting book.
     *
     * Held to the same rules as the sales journal: a month still running has
     * no journal, nor has one that bought nothing, and every copy taken out is
     * written down with a fingerprint of what it said.
     */
    public function purchasesPdf(Request $request, string $month): Response
    {
        $period = $this->journalPeriod('purchases', $month, $rows);

        return $this->journal(
            $request,
            'purchases',
            $period,
            'admin.accounting.purchases-pdf',
            $this->purchaseJournalData($period, $rows),
            $this->purchaseFingerprint($rows),
            'achats',
        );
    }

    /**
     * What a month's purchase journal carries.
     *
     * The screen and the PDF both come through here, which is what keeps them
     * in step.
     *
     * @param  Collection<int, AccountingEntry>  $rows
     * @return array<string, mixed>
     */
    private function purchaseJournalData(CarbonImmutable $period, Collection $rows): array
    {
        return [
            'period' => $period,
            'rows' => $rows,
            'exVatCents' => $rows->sum(fn (AccountingEntry $entry): int => $entry->exVatCents()),
            'vatCents' => $rows->sum(fn (AccountingEntry $entry): int => $entry->vatCents()),
            'totalCents' => $rows->sum('total_cents'),
            'company' => CompanySetting::current(),
        ];
    }

    /**
     * A signature of the lines a purchase journal prints.
     *
     * Same idea as the sales one: only what is printed goes in, so a remark
     * left alone raises nothing while an amount or a deleted line does.
     *
     * @param  Collection<int, AccountingEntry>  $rows
     */
    private function purchaseFingerprint(Collection $rows): string
    {
        return hash('sha256', $rows->map(fn (AccountingEntry $entry): string => implode('|', [
            $entry->entered_on->format('Y-m-d'),
            (string) $entry->invoice_number,
            (string) $entry->client,
            (string) $entry->type,
            $entry->exVatCents(),
            $entry->vatCents(),
            $entry->total_cents,
            $entry->payment_method,
        ]))->join("\n"));
    }

    /**
     * The signature of a month's lines, whichever side of the accounts.
     *
     * The two shapes are hashed differently — an array row on one side, an
     * entry model on the other — but the question is the same, so the choice
     * is made once here rather than at each call site.
     *
     * @param  Collection<int, mixed>  $rows
     */
    private function fingerprintOf(string $section, Collection $rows): string
    {
        return $section === 'purchases'
            ? $this->purchaseFingerprint($rows)
            : $this->fingerprint($rows);
    }

    /**
     * The month a journal may be printed for, and the lines it would hold.
     *
     * Refuses a month still running, since two printings of it would not agree
     * and both would be filed, and a month with nothing in it, since an empty
     * sheet is not a document. The button that leads here is switched off for
     * the same two reasons.
     *
     * @param  Collection<int, mixed>|null  $rows  Filled in with the month's lines.
     */
    private function journalPeriod(string $section, string $month, ?Collection &$rows): CarbonImmutable
    {
        $period = $this->sectionPeriod($section, $month);

        abort_unless(AccountingPeriods::isClosed($period), 404);

        $rows = $section === 'purchases'
            ? $this->purchaseRowsOf($period)
            : $this->rowsOf($section, $period);

        abort_if($rows->isEmpty(), 404);

        return $period;
    }

    /**
     * Renders a journal and writes down that it was taken.
     *
     * Both sections come through here, so neither can drift from the other on
     * the page shape or on what is recorded.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $stem  The French word the file is named after.
     */
    private function journal(
        Request $request,
        string $section,
        CarbonImmutable $period,
        string $view,
        array $data,
        string $fingerprint,
        string $stem,
    ): Response {
        // Landscape on both: neither journal stands upright without cutting a
        // client or a supplier name in half.
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        // Written down before the file is handed over: an accounting book
        // leaving the admin is worth a trail.
        AccountingJournalDownload::record(
            $section,
            AccountingPeriods::key($period),
            $fingerprint,
            $request->user()?->id,
        );

        return $pdf->download($stem.'-'.AccountingPeriods::key($period).'.pdf');
    }

    /**
     * What a month's journal carries.
     *
     * The same lines and the same totals as the page: a document that said
     * something else would be worth nothing. The screen and the PDF both come
     * through here, which is what keeps them in step.
     *
     * @return array<string, mixed>
     */
    private function journalData(CarbonImmutable $period): array
    {
        $rows = $this->rowsOf('sales', $period);

        // Refunds are listed but join no total, so the sums run on the
        // counted lines while `rows` keeps everything to print.
        $counted = $rows->where('counts', true);

        return [
            'period' => $period,
            'rows' => $rows,
            'refunded' => $rows->count() - $counted->count(),
            'totalCents' => $counted->sum('total_cents'),
            'feesCents' => $counted->sum('fees_cents'),
            'company' => CompanySetting::current(),
        ];
    }

    /** Records an entry typed by hand, and returns to the month it belongs to. */
    public function storeEntry(StoreAccountingEntryRequest $request, string $section, string $month): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        AccountingEntry::query()->create($request->entryPayload($section));

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry added.');
    }

    /**
     * Corrects an entry.
     *
     * `keepAuthor` leaves the original author in place: correcting an entry
     * does not make you the one who wrote it.
     */
    public function updateEntry(StoreAccountingEntryRequest $request, string $section, string $month, AccountingEntry $entry): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        // A sales URL must not reach a purchases entry, whatever the id says.
        abort_unless($entry->section === $section, 404);

        $entry->update($request->entryPayload($section, keepAuthor: true));

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry updated.');
    }

    /** Removes an entry. Orders are never touched here, only hand-written lines. */
    public function destroyEntry(string $section, string $month, AccountingEntry $entry): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        abort_unless($entry->section === $section, 404);

        $entry->delete();

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry deleted.');
    }

    /**
     * Reads the section and month out of the URL, or 404s.
     *
     * The entry routes take the section as a parameter rather than having one
     * route each, so it is checked here rather than trusted.
     */
    private function sectionPeriod(string $section, string $month): CarbonImmutable
    {
        abort_unless(in_array($section, ['sales', 'purchases'], true), 404);

        $period = AccountingPeriods::parse($month);

        abort_if($period === null, 404);

        return $period;
    }

    /**
     * The month's sales, in the order they were placed.
     *
     * The order date decides the month, not the shipping date: it is the one
     * the invoice carries. A draft is not a sale and a test order counts
     * nowhere; a refund does count as a line, since the accounts have to see
     * it rather than lose it.
     *
     * @return Collection<int, Order>
     */
    private function salesOf(CarbonImmutable $period): Collection
    {
        return $this->soldQuery()
            ->with('user', 'marketplace')
            ->whereBetween('created_at', [$period, $period->endOfMonth()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * What counts as a sale.
     *
     * A draft is not one, and a test order counts nowhere. A refund stays:
     * the accounts must see it go by, even though it adds to no total.
     */
    private function soldQuery(): Builder
    {
        return Order::query()
            ->where('status', '!=', 'draft')
            ->excludingTest();
    }

    /** The heading, and the browser tab. */
    private function title(string $section): string
    {
        return $section === 'sales' ? 'Sales' : 'Purchases';
    }

    /** The sentence under the heading, saying what the section holds. */
    private function lede(string $section): string
    {
        return $section === 'sales'
            ? 'What the shop took in: orders billed, VAT collected, and what each month adds up to.'
            : 'What the shop paid out: supplier orders received, VAT paid, and what each month adds up to.';
    }
}
