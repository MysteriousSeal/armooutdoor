<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'sort_order',
])]
class BlogCategory extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    public function localizedName(): string
    {
        return $this->name[app()->getLocale()] ?? $this->name['fr'] ?? '';
    }

    public function localizedDescription(): string
    {
        return $this->description[app()->getLocale()] ?? $this->description['fr'] ?? '';
    }
}
