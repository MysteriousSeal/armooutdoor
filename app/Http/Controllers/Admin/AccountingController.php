<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AccountingPeriods;
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
        ];
    }

    /** @return array<string, mixed> */
    private function monthData(string $section, string $month): array
    {
        $period = AccountingPeriods::parse($month);

        abort_if($period === null, 404);

        return [
            'section' => $section,
            'title' => $this->title($section),
            'period' => $period,
        ];
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
