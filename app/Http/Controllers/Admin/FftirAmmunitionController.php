<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Caliber;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\FftirAmmunition;
use App\Models\FftirAmmunitionStockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class FftirAmmunitionController extends Controller
{
    public function index(Request $request): View
    {
        $ammunitions = FftirAmmunition::query()->with('stockMovements')->orderBy('brand')->orderBy('caliber')->get();

        // Stats always reflect every caliber, whichever one the table is
        // currently filtered to: a chip should never make its own number
        // disappear.
        $caliberStats = $ammunitions
            ->groupBy(fn (FftirAmmunition $ammunition) => $ammunition->caliber->value)
            ->map(function ($group) {
                $priced = $group
                    ->flatMap(fn (FftirAmmunition $ammunition) => $ammunition->stockMovements)
                    ->filter(fn ($movement) => $movement->delta > 0 && $movement->total_price_cents !== null);

                $pricedRounds = $priced->sum('delta');

                return [
                    'caliber' => $group->first()->caliber,
                    'quantity' => $group->sum('quantity'),
                    'average_price_cents' => $pricedRounds > 0
                        ? (int) round($priced->sum('total_price_cents') / $pricedRounds)
                        : null,
                ];
            })
            ->sortBy(fn (array $stat) => $stat['caliber']->value)
            ->values();

        $activeCaliber = Caliber::tryFrom((string) $request->query('caliber'));

        if ($activeCaliber !== null) {
            $ammunitions = $ammunitions->filter(fn (FftirAmmunition $ammunition) => $ammunition->caliber === $activeCaliber)->values();
        }

        return view('admin.fftir.ammunitions.index', [
            'ammunitions' => $ammunitions,
            'caliberStats' => $caliberStats,
            'activeCaliber' => $activeCaliber,
        ]);
    }

    public function create(): View
    {
        return view('admin.fftir.ammunitions.form', [
            'ammunition' => new FftirAmmunition,
        ]);
    }

    public function edit(FftirAmmunition $ammunition): View
    {
        return view('admin.fftir.ammunitions.form', [
            'ammunition' => $ammunition,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'caliber' => ['required', new Enum(Caliber::class)],
            'denomination' => ['required', 'string', 'max:80'],
        ]);

        $ammunition = FftirAmmunition::query()->create($validated);
        AdminActivityLog::record('ammunition.created', $ammunition, 'Created ammunition '.$ammunition->brand.' '.$ammunition->denomination);

        return redirect()
            ->route('admin.fftir.ammunitions.index')
            ->with('status', 'Ammunition added.');
    }

    public function update(Request $request, FftirAmmunition $ammunition): RedirectResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'caliber' => ['required', new Enum(Caliber::class)],
            'denomination' => ['required', 'string', 'max:80'],
        ]);

        $ammunition->update($validated);
        AdminActivityLog::record('ammunition.updated', $ammunition, 'Updated ammunition '.$ammunition->brand.' '.$ammunition->denomination);

        return redirect()
            ->route('admin.fftir.ammunitions.index')
            ->with('status', 'Ammunition updated.');
    }

    public function addStock(Request $request, FftirAmmunition $ammunition): RedirectResponse
    {
        $bag = 'stock'.$ammunition->id;

        $validated = $request->validateWithBag($bag, [
            'delta' => ['required', 'integer', 'not_in:0'],
            'total_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $quantityBefore = $ammunition->quantity;
        $quantityAfter = $quantityBefore + $validated['delta'];

        if ($quantityAfter < 0) {
            return back()->withErrors(['delta' => 'That would take stock below zero.'], $bag)->withInput();
        }

        $ammunition->update(['quantity' => $quantityAfter]);

        // A price paid only means something when stock comes in: removing
        // stock (a negative delta, outside of session usage) has nothing to
        // have been bought for.
        $totalPriceCents = $validated['delta'] > 0 && filled($validated['total_price'] ?? null)
            ? (int) round((float) $validated['total_price'] * 100)
            : null;

        FftirAmmunitionStockMovement::query()->create([
            'fftir_ammunition_id' => $ammunition->id,
            'delta' => $validated['delta'],
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'total_price_cents' => $totalPriceCents,
            'note' => $validated['note'] ?? null,
            'user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.fftir.ammunitions.index')
            ->with('status', 'Stock updated.');
    }

    public function stockHistory(FftirAmmunition $ammunition): View
    {
        return view('admin.fftir.ammunitions.stock-history', [
            'ammunition' => $ammunition,
            'movements' => $ammunition->stockMovements()->with('user')->latest('created_at')->paginate(30),
        ]);
    }
}
