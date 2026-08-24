<?php

namespace App\Support;

use App\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Model;

/**
 * La raison d'un mouvement de stock, le temps que le mouvement ait lieu.
 *
 * L'observateur voit la quantité changer mais pas pourquoi : c'est l'appelant
 * qui l'annonce, en enveloppant son code existant sans le modifier.
 *
 *     StockContext::during(
 *         StockMovementReason::OrderPlaced,
 *         subject: $order,
 *         callback: fn () => $allocator->allocate(...),
 *     );
 *
 * Hors de tout appel, l'observateur enregistre Unattributed : le journal
 * reste complet même quand un chemin nouveau ne s'est pas présenté.
 */
class StockContext
{
    private static ?StockMovementReason $reason = null;

    private static ?Model $subject = null;

    private static ?string $note = null;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function during(
        StockMovementReason $reason,
        callable $callback,
        ?Model $subject = null,
        ?string $note = null,
    ): mixed {
        $previousReason = self::$reason;
        $previousSubject = self::$subject;
        $previousNote = self::$note;

        self::$reason = $reason;
        self::$subject = $subject;
        self::$note = $note;

        try {
            return $callback();
        } finally {
            // Une exception ne doit pas laisser sa raison au mouvement suivant.
            self::$reason = $previousReason;
            self::$subject = $previousSubject;
            self::$note = $previousNote;
        }
    }

    public static function reason(): StockMovementReason
    {
        return self::$reason ?? StockMovementReason::Unattributed;
    }

    public static function subject(): ?Model
    {
        return self::$subject;
    }

    public static function note(): ?string
    {
        return self::$note;
    }
}
