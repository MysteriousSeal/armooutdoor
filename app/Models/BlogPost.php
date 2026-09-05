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
    'image_credit',
    'status',
    'published_at',
    'meta_title',
    'meta_description',
    'sources',
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
            'sources' => 'array',
        ];
    }

    /**
     * The sources worth showing: rows with a real URL, label falling back
     * to the link's host so an unlabelled source still reads as something.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function sourcesList(): array
    {
        return collect($this->sources ?? [])
            ->filter(fn ($source): bool => is_array($source) && filled($source['url'] ?? null))
            ->map(fn (array $source): array => [
                'url' => $source['url'],
                'label' => filled($source['label'] ?? null)
                    ? $source['label']
                    : (parse_url($source['url'], PHP_URL_HOST) ?: $source['url']),
            ])
            ->values()
            ->all();
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

    /**
     * La mention telle qu'elle s'affiche.
     *
     * Le champ ne contient que le nom ; le « Photo © » est ajouté ici pour
     * que toutes les mentions se ressemblent, quelle que soit la façon dont
     * chacune a été saisie. La normalisation à l'écriture retire déjà un
     * préfixe tapé à la main, mais on se garde aussi ici : d'anciennes lignes
     * peuvent en porter un, et personne ne veut lire « Photo © Photo © ».
     */
    public function imageCreditLine(): string
    {
        $credit = trim((string) $this->image_credit);

        if ($credit === '') {
            return '';
        }

        return __('store.blog_image_credit_prefix').' '.self::stripCreditPrefix($credit);
    }

    /** Retire un « Photo © », « photo© » ou « © » de tête. */
    public static function stripCreditPrefix(string $credit): string
    {
        return trim((string) preg_replace('/^\s*(photo\s*)?©\s*/iu', '', trim($credit)));
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
