<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'blog_category_id',
    'slug',
    'title',
    'excerpt',
    'body',
    'image',
    'status',
    'published_at',
    'meta_title',
    'meta_description',
])]
class BlogPost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'body' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Ce qu'un visiteur a le droit de voir.
     *
     * La règle tient en trois conditions et ne doit exister qu'ici : la liste,
     * la page d'article, le plan du site et le renvoi depuis une fiche produit
     * passent tous par là. Un article daté du futur est un brouillon qui se
     * publiera tout seul — personne d'autre n'a besoin de le savoir.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('blog_post_product.sort_order');
    }

    public function isVisible(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    /** Publié, mais pas encore : l'état qu'aucune liste ne montre. */
    public function isScheduled(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function localizedTitle(): string
    {
        return $this->title[app()->getLocale()] ?? $this->title['fr'] ?? '';
    }

    public function localizedExcerpt(): string
    {
        return $this->excerpt[app()->getLocale()] ?? $this->excerpt['fr'] ?? '';
    }

    public function localizedBody(): string
    {
        return $this->body[app()->getLocale()] ?? $this->body['fr'] ?? '';
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->localizedTitle();
    }

    public function metaDescription(): string
    {
        return $this->meta_description ?: $this->localizedExcerpt();
    }

    public function heroUrl(): string
    {
        return $this->image ? asset('images/'.$this->image) : '';
    }

    public function cardUrl(): string
    {
        return $this->image ? ImageThumbnailer::urlFor($this->image) : '';
    }
}
