<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountingEntryRequest;
use App\Models\AccountingEntry;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Support\AccountingPeriods;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The accounts: what came in, what went out.
 *
 * The pages hold the place in the navigation and fix the address; the
 * figures come next.
 */
class AccountingController extends Controller
{
    public function sales(): View
    {
        return view('admin.accounting.index', $this->listData('sales'));
    }

    public function purchases(): View
    {
        return view('admin.accounting.index', $this->listData('purchases'));
    }

    public function salesMonth(string $month): View
    {
        return view('admin.accounting.month', $this->monthData('sales', $month));
    }

    public function purchasesMonth(string $month): View
    {
        return view('admin.accounting.month', $this->monthData('purchases', $month));
    }

    /** @return array<string, mixed> */
    private function listData(string $section): array
    {
        return [
            'section' => $section,
            'title' => $this->title($section),
            'lede' => $this->lede($section),
            // Grouped by year: past twelve months, one long column of months
            // no longer says where a financial year begins.
            'years' => AccountingPeriods::months()->groupBy(fn ($month): string => $month->format('Y')),
            'counts' => $section === 'sales' ? $this->salesCountByMonth() : collect(),
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
    private function salesCountByMonth(string $section = 'sales'): Collection
    {
        $orders = $this->countByMonth($this->soldQuery(), 'created_at');
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
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
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

    /** @return array<string, mixed> */
    private function monthData(string $section, string $month): array
    {
        $period = AccountingPeriods::parse($month);

        abort_if($period === null, 404);

        $data = [
            'section' => $section,
            'title' => $this->title($section),
            'period' => $period,
        ];

        if ($section === 'sales') {
            $data['rows'] = $this->rowsOf($section, $period);
            $data['entryTypes'] = AccountingEntry::TYPES;
            $data['paymentMethods'] = AccountingEntry::PAYMENT_METHODS;
        }

        return $data;
    }

    /**
     * The month's lines: the orders and the hand-written entries.
     *
     * Sorted by date, among one another — an entry written by hand is not an
     * appendix to the table, it is a line of it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsOf(string $section, CarbonImmutable $period): Collection
    {
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
    public function salesPdf(string $month): Response
    {
        $period = $this->sectionPeriod('sales', $month);

        // A month still running has no journal: two printings of the same
        // month would not agree, and both would be filed.
        abort_unless(AccountingPeriods::isClosed($period), 404);

        $pdf = Pdf::loadView('admin.accounting.sales-pdf', $this->journalData($period))
            // Ten columns do not stand upright without cutting names in half.
            ->setPaper('a4', 'landscape');

        return $pdf->download('ventes-'.AccountingPeriods::key($period).'.pdf');
    }

    /**
     * What a month's journal carries.
     *
     * The same lines and the same totals as the page: a document that said
     * something else would be worth nothing.
     *
     * @return array<string, mixed>
     */
    private function journalData(CarbonImmutable $period): array
    {
        $rows = $this->rowsOf('sales', $period);
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

    public function storeEntry(StoreAccountingEntryRequest $request, string $section, string $month): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        AccountingEntry::query()->create($request->entryPayload($section, $period));

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry added.');
    }

    public function updateEntry(StoreAccountingEntryRequest $request, string $section, string $month, AccountingEntry $entry): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        abort_unless($entry->section === $section, 404);

        $entry->update($request->entryPayload($section, $period, keepAuthor: true));

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry updated.');
    }

    public function destroyEntry(string $section, string $month, AccountingEntry $entry): RedirectResponse
    {
        $period = $this->sectionPeriod($section, $month);

        abort_unless($entry->section === $section, 404);

        $entry->delete();

        return redirect()
            ->route('admin.accounting.'.$section.'.month', ['month' => AccountingPeriods::key($period)])
            ->with('status', 'Entry deleted.');
    }

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

    private function title(string $section): string
    {
        return $section === 'sales' ? 'Sales' : 'Purchases';
    }

    private function lede(string $section): string
    {
        return $section === 'sales'
            ? 'What the shop took in: orders billed, VAT collected, and what each month adds up to.'
            : 'What the shop paid out: supplier orders received, VAT paid, and what each month adds up to.';
    }
}
