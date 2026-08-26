<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountingEntryRequest;
use App\Models\AccountingEntry;
use App\Models\Order;
use App\Support\AccountingPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * La comptabilité : ce qui est entré, ce qui est sorti.
 *
 * Les pages sont encore vides. Elles existent d'abord pour tenir la place
 * dans la navigation et fixer l'adresse ; les chiffres viendront dedans.
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
            // Groupés par année : passé douze mois, une seule colonne de mois
            // ne dit plus où commence l'exercice.
            'years' => AccountingPeriods::months()->groupBy(fn ($month): string => $month->format('Y')),
            'counts' => $section === 'sales' ? $this->salesCountByMonth() : collect(),
        ];
    }

    /**
     * Combien d'écritures par mois, en deux requêtes.
     *
     * Une par mois affiché ferait douze requêtes pour une liste qui n'affiche
     * qu'un nombre. Les écritures saisies comptent comme les commandes : la
     * liste dit combien de lignes il y a à lire dans le mois.
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
        // SQLite en développement, MySQL en production : les deux ne
        // découpent pas une date de la même façon.
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
     * Les lignes du mois : les commandes et les écritures à la main.
     *
     * Rangées à la date, les unes parmi les autres — une écriture saisie
     * n'est pas une annexe du tableau, c'est une ligne du tableau.
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
            'total_cents' => $order->total_cents,
            'fees_cents' => ($order->marketplace_commission_cents ?? 0) + ($order->payment_fee_cents ?? 0),
            'payment' => 'Bank wire',
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
                'total_cents' => $entry->total_cents,
                'fees_cents' => $entry->fees_cents,
                'payment' => $entry->paymentLabel(),
                'remark' => $entry->remark ?: '',
                'counts' => true,
                'refunded' => false,
            ]);

        return $orders->toBase()
            ->merge($entries->toBase())
            ->sortBy([['date', 'asc'], ['invoice', 'asc']])
            ->values();
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
     * Les ventes du mois, dans l'ordre où elles ont été passées.
     *
     * La date de commande décide du mois, pas celle d'expédition : c'est
     * celle que porte la facture. Les brouillons ne sont pas des ventes et
     * les commandes de test ne comptent nulle part ; un remboursement, si :
     * la comptabilité doit le voir, pas le perdre.
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
     * Ce qui compte comme une vente.
     *
     * Un brouillon n'en est pas une, une commande de test ne compte nulle
     * part. Un remboursement reste : la comptabilité doit le voir passer,
     * même s'il ne s'ajoute à aucun total.
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
