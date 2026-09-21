<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What will be written on Cdiscount for a product.
 *
 * Nothing is sent from here: Octopia takes its own Excel template, filled in
 * and uploaded by hand. This holds the category that describes the product
 * and the answers to that category's attributes, kept from one export to the
 * next. It sits beside the product, as the Vinted listing does: what a
 * marketplace asks is not what the catalogue knows.
 */
#[Fillable(['product_id', 'octopia_template_id', 'values', 'per_variant', 'offer', 'description'])]
class CdiscountListing extends Model
{
    /** What the article is like, as Octopia words it. */
    public const CONDITIONS = [
        'New' => 'New',
        'UsedLikeNew' => 'Used, like new',
        'UsedVeryGoodState' => 'Used, very good state',
        'UsedAverageState' => 'Used, average state',
        'RefurbishedLikeNew' => 'Refurbished, like new',
        'RefurbishedVeryGoodState' => 'Refurbished, very good state',
        'RefurbishedCorrectState' => 'Refurbished, correct state',
    ];

    /**
     * The ways this shop delivers, under the codes Octopia knows them by:
     * Envoi suivi, Recommandé and Mondial Relay are what its Cdiscount
     * account is set up for. The tracked one is the mandatory one.
     */
    public const DELIVERY_MODES = [
        'THD' => 'Tracked home delivery (Envoi suivi)',
        'SHD' => 'Signed home delivery (Recommandé)',
        'PPMR' => 'Mondial Relay pickup point',
    ];

    /**
     * What a product's offer is until its seller decides otherwise: the shop's
     * price plus 40% to cover Cdiscount's commission, a day to prepare, and
     * every way of delivering offered at a set cost. The mondial relay pickup
     * is free.
     */
    public const DEFAULT_OFFER = [
        'condition' => 'New',
        'markup' => 40.0,
        'preparation_days' => 1,
        'delivery' => [
            'THD' => ['cost' => 3.0, 'additional' => null],
            'SHD' => ['cost' => 5.0, 'additional' => null],
            'PPMR' => ['cost' => 0.0, 'additional' => null],
        ],
    ];

    protected $attributes = [
        'values' => '[]',
        'per_variant' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'per_variant' => 'array',
            'offer' => 'array',
        ];
    }

    /**
     * What the seller decides about the offers of this product. Until they
     * have decided anything, the defaults stand.
     *
     * @return array{condition: string, markup: float, preparation_days: ?int, delivery: array<string, array{cost: float, additional: ?float}>}
     */
    public function offerSettings(): array
    {
        if ($this->offer === null) {
            return self::DEFAULT_OFFER;
        }

        $offer = (array) $this->offer;

        return [
            'condition' => (string) ($offer['condition'] ?? self::DEFAULT_OFFER['condition']),
            // A percentage over the shop's price: Cdiscount takes a commission.
            'markup' => (float) ($offer['markup'] ?? self::DEFAULT_OFFER['markup']),
            // Saved settings are the seller's own: what they left out stays out.
            'preparation_days' => isset($offer['preparation_days']) && $offer['preparation_days'] !== '' ? (int) $offer['preparation_days'] : null,
            'delivery' => (array) ($offer['delivery'] ?? []),
        ];
    }

    /**
     * What an offer cannot go without. The tracked home delivery is the one
     * mode Octopia makes mandatory.
     *
     * @return list<string>
     */
    public function offerMissing(): array
    {
        $offer = $this->offerSettings();
        $missing = [];

        if ($offer['preparation_days'] === null) {
            $missing[] = 'Preparation time';
        }

        if (! isset($offer['delivery']['THD']['cost'])) {
            $missing[] = 'Tracked delivery';
        }

        return $missing;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OctopiaTemplate::class, 'octopia_template_id');
    }

    /** The answers a variant gives for itself. */
    public function variants(): HasMany
    {
        return $this->hasMany(CdiscountListingVariant::class);
    }

    /**
     * What this product answers, attribute code by attribute code.
     *
     * @return array<string, string>
     */
    public function answers(): array
    {
        return array_map('strval', (array) ($this->values ?? []));
    }

    /**
     * The attributes answered variant by variant: the colour of a product
     * sold in three of them is three answers, its material one.
     *
     * @return list<string>
     */
    public function perVariantCodes(): array
    {
        return array_values(array_map('strval', (array) ($this->per_variant ?? [])));
    }

    /**
     * One variant's own answers, the product's standing where it says
     * nothing.
     *
     * @return array<string, string>
     */
    public function answersFor(?ProductVariant $variant): array
    {
        $answers = $this->answers();

        if ($variant === null) {
            return $answers;
        }

        $own = $this->variants->firstWhere('product_variant_id', $variant->id);

        foreach ($own?->answers() ?? [] as $code => $value) {
            if (filled($value)) {
                $answers[$code] = $value;
            }
        }

        return $answers;
    }
}
