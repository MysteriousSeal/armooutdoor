<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name',
    'line1',
    'postal_code',
    'city',
    'country',
    'hours',
    'sort_order',
])]
class RelayPoint extends Model
{
    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'line1' => $this->line1,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'hours' => $this->hours,
        ];
    }

    public function searchBlob(): string
    {
        return mb_strtolower(implode(' ', [
            $this->name,
            $this->line1,
            $this->postal_code,
            $this->city,
        ]));
    }

    /**
     * Opening hours as one [day, "HH:MM-HH:MM[, HH:MM-HH:MM]"] pair per day.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function hoursLines(): array
    {
        if (! $this->hours) {
            return [];
        }

        return array_map(
            fn (string $line): array => array_pad(explode(' ', $line, 2), 2, ''),
            explode(' · ', $this->hours),
        );
    }
}
