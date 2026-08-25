<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use App\Support\PdfImageCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'product_id',
    'product_variant_id',
    'name',
    'sku',
    'supplier_reference',
    'quantity_ordered',
    'quantity_received',
    'unit_cost_cents',
])]
class PurchaseOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost_cents' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function quantityRemaining(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    /**
     * L'image à imprimer sur le bon de commande.
     *
     * Un chemin sur le disque, pas une URL : le générateur de PDF lit le
     * fichier. La variante d'abord, le produit ensuite, comme partout
     * ailleurs. Rien si le produit a été supprimé depuis, ou si le fichier
     * n'est plus là — une image manquante ne doit pas casser le document.
     */
    public function imagePath(): ?string
    {
        $image = $this->variant?->image ?: $this->product?->image;

        if (! is_string($image) || $image === '') {
            return null;
        }

        if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
            return $image;
        }

        // Réduite pour l'impression : le générateur de PDF décode le fichier
        // entier avant de le ramener à 36 px, et une photo de produit pleine
        // taille lui coûtait une demi-seconde.
        $thumbnail = ImageThumbnailer::absoluteThumbnailPath($image);
        $source = is_file($thumbnail) ? $thumbnail : public_path('images/'.$image);

        return PdfImageCache::pathFor($source);
    }

    public function lineTotalCents(): int
    {
        return $this->quantity_ordered * $this->unit_cost_cents;
    }
}
