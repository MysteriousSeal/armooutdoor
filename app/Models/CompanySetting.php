<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_name',
    'legal_form',
    'share_capital',
    'siret',
    'vat_number',
    'vat_exempt',
    'address',
    'publication_director',
    'host_name',
    'host_address',
    'host_phone',
    'contact_email',
    'phone',
    'return_address',
])]
class CompanySetting extends Model
{
    /**
     * Fields that are legitimately optional (e.g. an auto-entrepreneur has no
     * share capital) and shouldn't block isComplete() or show a placeholder.
     */
    private const OPTIONAL_FIELDS = ['share_capital'];

    protected function casts(): array
    {
        return [
            'vat_exempt' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function value(string $field): string
    {
        if (trim((string) $this->{$field}) !== '') {
            return $this->{$field};
        }

        return in_array($field, self::OPTIONAL_FIELDS, true) ? '' : '['.$this->placeholder($field).']';
    }

    public function vatMention(): string
    {
        return $this->vat_exempt
            ? 'TVA non applicable, art. 293 B du CGI'
            : 'Numéro de TVA intracommunautaire : '.$this->value('vat_number');
    }

    public function isComplete(): bool
    {
        foreach ($this->getFillable() as $field) {
            if ($field === 'vat_exempt' || in_array($field, self::OPTIONAL_FIELDS, true)) {
                continue;
            }

            if ($field === 'vat_number' && $this->vat_exempt) {
                continue;
            }

            if (trim((string) $this->{$field}) === '') {
                return false;
            }
        }

        return true;
    }

    private function placeholder(string $field): string
    {
        return match ($field) {
            'company_name' => 'Raison sociale',
            'legal_form' => 'Forme juridique',
            'share_capital' => 'Capital social',
            'siret' => 'SIRET',
            'vat_number' => 'Numéro de TVA',
            'address' => 'Adresse du siège social',
            'publication_director' => 'Nom du directeur de la publication',
            'host_name' => "Nom de l'hébergeur",
            'host_address' => "Adresse de l'hébergeur",
            'host_phone' => "Téléphone de l'hébergeur",
            'contact_email' => 'Email de contact',
            'phone' => 'Numéro de téléphone',
            'return_address' => 'Adresse de retour',
            default => $field,
        };
    }
}
