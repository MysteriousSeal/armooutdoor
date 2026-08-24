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

    /**
     * Les rubriques telles qu'elles sont semées.
     *
     * Sert de repli quand la table n'est pas encore lisible : les routes se
     * déclarent avant que la base ne soit forcément migrée, et une exception
     * à cet instant ferait échouer jusqu'à `php artisan migrate` lui-même.
     *
     * @var list<string>
     */
    public const SEEDED_SLUGS = ['conseils', 'actualites', 'essais', 'reglementation'];

    /**
     * L'expression qui distingue une rubrique d'un article dans l'URL.
     *
     * `/blog/{slug}` sert aux deux : la route rubrique passe en premier et ne
     * répond que pour ces valeurs, le reste retombe sur la route article.
     */
    public static function routeSlugPattern(): string
    {
        try {
            $slugs = static::query()->orderBy('sort_order')->pluck('slug')->all();
        } catch (\Throwable) {
            $slugs = self::SEEDED_SLUGS;
        }

        if ($slugs === []) {
            $slugs = self::SEEDED_SLUGS;
        }

        return implode('|', array_map('preg_quote', $slugs));
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
