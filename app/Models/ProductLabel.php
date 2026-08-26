<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The wording printed on a product's label.
 *
 * One row per product, and only while it says something: a label emptied of
 * every field is deleted rather than kept as four nulls, so the row's
 * existence and "this product has wording" mean the same thing.
 */
#[Fillable(['product_id', 'title', 'subtitle', 'composition', 'mention'])]
class ProductLabel extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Whether the label has anything left to print. */
    public function isBlank(): bool
    {
        return blank($this->title)
            && blank($this->subtitle)
            && blank($this->composition)
            && blank($this->mention);
    }
}
