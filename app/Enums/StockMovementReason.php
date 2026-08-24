<?php

namespace App\Enums;

/**
 * Pourquoi une quantité a bougé.
 *
 * L'observateur voit le chiffre changer mais ne peut pas deviner la raison :
 * c'est l'appelant qui la déclare, via StockContext. Unattributed est le
 * filet — une ligne sans raison veut dire qu'un chemin nouveau touche au
 * stock sans se présenter, pas que le mouvement n'a pas eu lieu.
 */
enum StockMovementReason: string
{
    case OrderPlaced = 'order_placed';
    case ManualOrder = 'manual_order';
    case BackorderPartial = 'backorder_partial';
    case DraftValidated = 'draft_validated';
    case PurchaseOrderReceived = 'purchase_order_received';
    case ManualAdjustment = 'manual_adjustment';
    case ProductEdited = 'product_edited';
    case ApiUpdate = 'api_update';
    case Unattributed = 'unattributed';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'Customer order',
            self::ManualOrder => 'Manual order',
            self::BackorderPartial => 'Backorder',
            self::DraftValidated => 'Draft validated',
            self::PurchaseOrderReceived => 'Purchase order received',
            self::ManualAdjustment => 'Manual adjustment',
            self::ProductEdited => 'Product edited',
            self::ApiUpdate => 'API update',
            self::Unattributed => 'Unattributed',
        };
    }

    /**
     * Le sens habituel du mouvement, pour la couleur de la pastille. Un
     * ajustement peut aller dans les deux sens, d'où la troisième valeur.
     *
     * @return 'in'|'out'|'adjustment'
     */
    public function direction(): string
    {
        return match ($this) {
            self::PurchaseOrderReceived => 'in',
            self::OrderPlaced, self::ManualOrder, self::BackorderPartial, self::DraftValidated => 'out',
            self::ManualAdjustment, self::ProductEdited, self::ApiUpdate, self::Unattributed => 'adjustment',
        };
    }
}
