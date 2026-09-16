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
    public function index(): View
    {
        return view('admin.fftir.ammunitions.index', [
            'ammunitions' => FftirAmmunition::query()->with('stockMovements')->orderBy('brand')->orderBy('caliber')->get(),
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
