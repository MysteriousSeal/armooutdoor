<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What Octopia asks of a Cdiscount category, one per category.
 *
 * Octopia's attributes are the category's own: a balaclava is asked for its
 * hat size, a target is not. They are read from Octopia's API and kept here,
 * with when they were read, so no page has to ask again.
 */
#[Fillable(['code', 'name', 'fields', 'synced_at'])]
class OctopiaTemplate extends Model
{
    /** The columns the export fills from the catalogue, by their field code. */
    public const CATALOGUE_CODES = [
        'gtin',
        'sellerProductReference',
        'title',
        'description',
        'brand',
        'richMarketingDescription',
        'gtinReference',
        'sellerPictureUrls_1',
        'sellerPictureUrls_2',
        'sellerPictureUrls_3',
        'sellerPictureUrls_4',
        'sellerPictureUrls_5',
        'sellerPictureUrls_6',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** What was sent to Octopia for this category, the latest first. */
    public function submissions(): HasMany
    {
        return $this->hasMany(OctopiaSubmission::class)->latest('id');
    }

    /** The products described by this category. */
    public function listings(): HasMany
    {
        return $this->hasMany(CdiscountListing::class);
    }

    /**
     * The columns nobody can fill from the catalogue: the category's own
     * attributes, answered product by product.
     *
     * @return list<array<string, mixed>>
     */
    public function attributeFields(): array
    {
        return array_values(array_filter(
            $this->fields ?? [],
            fn (array $field): bool => ! in_array($field['code'], self::CATALOGUE_CODES, true),
        ));
    }

    /** @return list<array<string, mixed>> */
    public function requiredAttributeFields(): array
    {
        return array_values(array_filter($this->attributeFields(), fn (array $field): bool => $field['required']));
    }
}
