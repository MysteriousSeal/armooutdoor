<?php

namespace App\Support\Octopia;

/**
 * Octopia's category properties, put in the shape a template's columns have.
 *
 * The rest of the shop asks a category's attributes from `fields`: a code, a
 * label, whether it is required, and a list of options for a closed one. A
 * template read from Excel and a category read from the API both end up there,
 * so the product page does not care which one it is looking at.
 */
class ApiFields
{
    /**
     * @param  list<array<string, mixed>>  $properties  as GET /categories/{code}/properties returns them
     * @return list<array<string, mixed>>
     */
    public static function fromProperties(array $properties): array
    {
        usort($properties, fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        $fields = [];

        foreach ($properties as $property) {
            $code = trim((string) ($property['propertyReference'] ?? ''));
            $label = trim((string) ($property['label'] ?? ''));

            if ($code === '' || $label === '') {
                continue;
            }

            $ranged = (bool) ($property['isRanged'] ?? false);
            $multiple = (bool) ($property['isMultiple'] ?? false);

            $fields[] = [
                // No column: nothing here is a spreadsheet.
                'column' => null,
                'code' => $code,
                'label' => $label,
                'required' => ($property['necessity'] ?? null) === 'Mandatory',
                'kind' => ($multiple ? 'multi' : 'mono').($ranged ? 'ranged' : ''),
                'constraint' => self::constraint($property),
                'options' => $ranged ? array_values(array_map('strval', $property['choices'] ?? [])) : [],
                'necessity' => $property['necessity'] ?? null,
                'is_variation' => (bool) ($property['isVariation'] ?? false),
            ];
        }

        return $fields;
    }

    /** The constraint in words, as the template gives it under each column. */
    private static function constraint(array $property): string
    {
        $parts = [];

        if ($property['isNumeric'] ?? false) {
            $parts[] = 'Numérique';
        }

        if (filled($property['unit'] ?? null)) {
            $parts[] = 'Unité : '.$property['unit'];
        }

        if ($property['isMultiple'] ?? false) {
            $parts[] = 'Plusieurs valeurs';
        }

        return implode(' · ', $parts);
    }
}
