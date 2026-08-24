<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\StockContext;
use Illuminate\Support\Facades\Auth;

/**
 * Écrit une ligne de journal dès qu'une quantité change.
 *
 * Poser l'appel dans chacun des neuf endroits qui touchent au stock est
 * précisément ce qui a laissé AdminActivityLog incomplet : ici le journal
 * ne se contourne pas, et un chemin nouveau y entre sans rien faire.
 *
 * Une limite à connaître : une écriture par le query builder
 * (Product::query()->update(...)) ne déclenche aucun événement de modèle et
 * passerait donc à côté. Il n'en existe aucune aujourd'hui — la bannière de
 * dérive de la page d'historique est là pour le dire si cela changeait.
 */
class StockMovementObserver
{
    public function updated(Product|ProductVariant $stockable): void
    {
        if (! $stockable->wasChanged('quantity')) {
            return;
        }

        // On journalise ce qui a réellement bougé. Un produit à variantes ne
        // possède pas sa quantité : elle est recopiée de la somme de ses
        // déclinaisons par reconcileQuantity(), juste après le vrai mouvement.
        // Sans cette règle, une vente de déclinaison écrirait deux lignes.
        //
        // La question est posée à la base plutôt qu'à hasVariants() : la
        // relation chargée peut dater d'avant la suppression de la dernière
        // déclinaison, et c'est justement le moment où la remise à zéro du
        // produit doit être journalisée.
        if ($stockable instanceof Product && $stockable->variants()->exists()) {
            return;
        }

        $before = (int) $stockable->getOriginal('quantity');
        $after = (int) $stockable->quantity;

        $subject = StockContext::subject();

        StockMovement::query()->create([
            'product_id' => $stockable instanceof Product ? $stockable->id : $stockable->product_id,
            'product_variant_id' => $stockable instanceof ProductVariant ? $stockable->id : null,
            'variant_label' => $stockable instanceof ProductVariant ? ($stockable->label() ?: null) : null,
            'reason' => StockContext::reason(),
            'delta' => $after - $before,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => Auth::id(),
            'note' => StockContext::note(),
        ]);
    }
}
