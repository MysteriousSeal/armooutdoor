<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\AccountingPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
     * Combien de ventes par mois, en une requête.
     *
     * Une par mois affiché ferait douze requêtes pour une liste qui n'affiche
     * qu'un nombre.
     *
     * @return Collection<string, int>
     */
    private function salesCountByMonth(): Collection
    {
        $query = $this->soldQuery();

        // SQLite en développement, MySQL en production : les deux ne
        // découpent pas une date de la même façon.
        $month = $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

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
            $data['orders'] = $this->salesOf($period);
        }

        return $data;
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
