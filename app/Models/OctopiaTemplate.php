<?php

namespace App\Models;

use App\Support\Octopia\TemplateFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Octopia product template, one per Cdiscount category.
 *
 * Octopia gives out an Excel file per category, whose columns are that
 * category's own: a balaclava is asked for its hat size, a target is not.
 * The file is kept as it came so the export can hand back the same file
 * filled in; what was read out of it at upload time is kept here so no page
 * has to open it again.
 */
#[Fillable(['code', 'name', 'original_filename', 'path', 'sheet_path', 'first_data_row', 'fields'])]
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
            'first_data_row' => 'integer',
        ];
    }

    /** The products described by this category's template. */
    public function listings(): HasMany
    {
        return $this->hasMany(CdiscountListing::class);
    }

    public function file(): TemplateFile
    {
        return new TemplateFile(storage_path('app/private/'.$this->path));
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
