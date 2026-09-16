<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Caliber;
use App\Enums\WeaponType;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\FftirWeapon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class FftirWeaponController extends Controller
{
    public function index(): View
    {
        return view('admin.fftir.weapons.index', [
            'weapons' => FftirWeapon::query()
                ->with('sessionLines.ammunition.stockMovements')
                ->orderBy('brand')
                ->orderBy('model')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fftir.weapons.form', [
            'weapon' => new FftirWeapon,
        ]);
    }

    public function edit(FftirWeapon $weapon): View
    {
        return view('admin.fftir.weapons.form', [
            'weapon' => $weapon,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'caliber' => ['required', new Enum(Caliber::class)],
            'type' => ['required', new Enum(WeaponType::class)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $validated['price_cents'] = filled($validated['price'] ?? null) ? (int) round((float) $validated['price'] * 100) : null;
        unset($validated['price']);

        $weapon = FftirWeapon::query()->create($validated);
        AdminActivityLog::record('weapon.created', $weapon, 'Created weapon '.$weapon->brand.' '.$weapon->model);

        return redirect()
            ->route('admin.fftir.weapons.index')
            ->with('status', 'Weapon added.');
    }

    public function update(Request $request, FftirWeapon $weapon): RedirectResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'caliber' => ['required', new Enum(Caliber::class)],
            'type' => ['required', new Enum(WeaponType::class)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $validated['price_cents'] = filled($validated['price'] ?? null) ? (int) round((float) $validated['price'] * 100) : null;
        unset($validated['price']);

        $weapon->update($validated);
        AdminActivityLog::record('weapon.updated', $weapon, 'Updated weapon '.$weapon->brand.' '.$weapon->model);

        return redirect()
            ->route('admin.fftir.weapons.index')
            ->with('status', 'Weapon updated.');
    }
}
