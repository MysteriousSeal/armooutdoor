<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use DOMDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

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

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    /**
     * Reading time in minutes, from the body's word count at the ~200
     * words a minute of unhurried French prose. Never below one: a short
     * note still takes a minute to open and read.
     */
    public function readingMinutes(): int
    {
        preg_match_all('/\S+/u', strip_tags($this->localizedBody()), $words);

        return max(1, (int) ceil(count($words[0]) / 200));
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
            ->map(function (array $source): array {
                $host = preg_replace('/^www\./', '', (string) (parse_url($source['url'], PHP_URL_HOST) ?: $source['url']));

                return [
                    'url' => $source['url'],
                    'host' => $host,
                    'label' => filled($source['label'] ?? null) ? $source['label'] : $host,
                ];
            })
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

    /** Memo for annotatedBody(): the body it was built from, and the result. */
    private ?array $annotatedBodyMemo = null;

    /**
     * The body with an id on every h2, plus the list of those headings in
     * order, so the page can draw a jump nav without a second parse.
     *
     * The admin sanitizer strips ids on save on purpose, so they are added
     * here at render time from the heading text. DOMDocument rather than a
     * regex: the body is trusted sanitized HTML, and a parser keeps the
     * accented French text and the inline links exactly as stored. The
     * `<?xml encoding="UTF-8">` prologue is what stops libxml from reading
     * the fragment as Latin-1; only the wrapper div's children are written
     * back out, never a synthetic html/body document.
     *
     * Memoized against the body string itself, so a changed body or locale
     * is never served a stale parse, and the view can ask twice for free.
     *
     * @return array{html: string, headings: array<int, array{id: string, text: string}>}
     */
    public function annotatedBody(): array
    {
        $body = $this->localizedBody();

        if ($this->annotatedBodyMemo !== null && $this->annotatedBodyMemo['body'] === $body) {
            return $this->annotatedBodyMemo['result'];
        }

        $result = ['html' => $body, 'headings' => []];

        if (trim($body) !== '') {
            $document = new DOMDocument('1.0', 'UTF-8');
            $internalErrors = libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="UTF-8"><div id="blog-annotate-root">'.$body.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);

            $wrapper = $document->getElementById('blog-annotate-root');

            if ($wrapper !== null) {
                // Ids already used elsewhere on the article page: a heading
                // that slugs to one of these gets a numeric suffix instead.
                $taken = ['commentaires' => true];

                foreach ($document->getElementsByTagName('h2') as $heading) {
                    $text = trim($heading->textContent);

                    if ($text === '') {
                        continue;
                    }

                    $base = Str::slug($text) ?: 'section';
                    $id = $base;

                    for ($n = 2; isset($taken[$id]); $n++) {
                        $id = $base.'-'.$n;
                    }

                    $taken[$id] = true;
                    $heading->setAttribute('id', $id);
                    $result['headings'][] = ['id' => $id, 'text' => $text];
                }

                $html = '';

                foreach ($wrapper->childNodes as $child) {
                    $html .= $document->saveHTML($child);
                }

                $result['html'] = $html;
            }
        }

        $this->annotatedBodyMemo = ['body' => $body, 'result' => $result];

        return $result;
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
