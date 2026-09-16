<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShootingDistance;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\FftirAmmunition;
use App\Models\FftirAmmunitionStockMovement;
use App\Models\FftirSession;
use App\Models\FftirWeapon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FftirSessionController extends Controller
{
    public function index(): View
    {
        return view('admin.fftir.sessions.index', [
            'sessions' => FftirSession::query()
                ->with(['lines.weapon', 'lines.ammunition'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fftir.sessions.create', [
            'weapons' => FftirWeapon::query()->orderBy('brand')->orderBy('model')->get(),
            'ammunitions' => FftirAmmunition::query()->orderBy('brand')->orderBy('caliber')->get(),
            'distances' => ShootingDistance::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.weapon_id' => ['required', 'integer', 'exists:fftir_weapons,id'],
            'lines.*.ammunition_id' => ['nullable', 'integer', 'exists:fftir_ammunitions,id'],
            'lines.*.distance' => ['required', new Enum(ShootingDistance::class)],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        // Only a line that names a specific box draws down its stock: one
        // that only names a caliber is a memory of what was fired, not a
        // claim about which box it came from.
        $pricedLines = collect($validated['lines'])->filter(fn ($line) => $line['ammunition_id'] !== null);

        $neededByAmmunition = $pricedLines
            ->groupBy('ammunition_id')
            ->map(fn ($lines) => $lines->sum('quantity'));

        DB::transaction(function () use ($validated, $neededByAmmunition, $request): FftirSession {
            $weapons = FftirWeapon::query()
                ->whereIn('id', collect($validated['lines'])->pluck('weapon_id')->unique())
                ->get()
                ->keyBy('id');

            $ammunitions = FftirAmmunition::query()
                ->whereIn('id', $neededByAmmunition->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($validated['lines'] as $index => $line) {
                if ($line['ammunition_id'] === null) {
                    continue;
                }

                $weapon = $weapons[$line['weapon_id']];
                $ammunition = $ammunitions[$line['ammunition_id']];

                if ($ammunition->caliber !== $weapon->caliber) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.ammunition_id" => "{$ammunition->brand} {$ammunition->denomination} is {$ammunition->caliber->value}, but the {$weapon->brand} {$weapon->model} is {$weapon->caliber->value}.",
                    ]);
                }
            }

            foreach ($neededByAmmunition as $ammunitionId => $needed) {
                $ammunition = $ammunitions[$ammunitionId];

                if ($needed > $ammunition->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => "Not enough {$ammunition->brand} {$ammunition->denomination} in stock: need {$needed}, have {$ammunition->quantity}.",
                    ]);
                }
            }

            $session = FftirSession::query()->create([
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $session->lines()->create([
                    'fftir_weapon_id' => $line['weapon_id'],
                    'fftir_ammunition_id' => $line['ammunition_id'],
                    'caliber' => $weapons[$line['weapon_id']]->caliber,
                    'distance' => $line['distance'],
                    'quantity' => $line['quantity'],
                ]);

                if ($line['ammunition_id'] === null) {
                    continue;
                }

                $ammunition = $ammunitions[$line['ammunition_id']];
                $quantityBefore = $ammunition->quantity;
                $quantityAfter = $quantityBefore - $line['quantity'];

                $ammunition->update(['quantity' => $quantityAfter]);

                FftirAmmunitionStockMovement::query()->create([
                    'fftir_ammunition_id' => $ammunition->id,
                    'delta' => -$line['quantity'],
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'note' => 'Used in shooting session on '.$validated['date'],
                    'user_id' => $request->user()?->id,
                    'created_at' => now(),
                ]);
            }

            AdminActivityLog::record('fftir_session.created', $session, 'Logged shooting session on '.$validated['date']);

            return $session;
        });

        return redirect()
            ->route('admin.fftir.sessions.index')
            ->with('status', 'Session logged.');
    }

    public function destroy(FftirSession $session): RedirectResponse
    {
        DB::transaction(function () use ($session): void {
            $session->load('lines.ammunition');

            foreach ($session->lines as $line) {
                if ($line->ammunition === null) {
                    continue;
                }

                $ammunition = $line->ammunition;
                $quantityBefore = $ammunition->quantity;
                $quantityAfter = $quantityBefore + $line->quantity;

                $ammunition->update(['quantity' => $quantityAfter]);

                FftirAmmunitionStockMovement::query()->create([
                    'fftir_ammunition_id' => $ammunition->id,
                    'delta' => $line->quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'note' => 'Restored: session on '.$session->date->toDateString().' was deleted',
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            AdminActivityLog::record('fftir_session.deleted', null, 'Deleted shooting session on '.$session->date->toDateString());

            $session->delete();
        });

        return redirect()
            ->route('admin.fftir.sessions.index')
            ->with('status', 'Session deleted, stock restored.');
    }
}
