<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ShippingSetting;
use App\Support\Guides;
use App\Support\HomepageCatalog;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** The catalogue figure is rounded down to a multiple of this. */
    private const REFERENCE_STEP = 50;

    public function __invoke(): View
    {
        $shipping = ShippingSetting::current();

        // A threshold with no carrier flagged grants free shipping on nothing,
        // so there is no figure to advertise. Both surfaces read this, so
        // neither can promise what checkout would not honour.
        $thresholdCents = ($shipping->free_shipping_carrier_ids ?? []) === []
            ? null
            : $shipping->free_shipping_threshold_cents;
        // The cards need a name, an icon and a count — never a product. Loading
        // the catalogue to call `count()` on it hydrated every active product
        // with its variants and its suppliers on every visit to the homepage;
        // counting in SQL asks the same question without the answer in memory.
        $categories = Category::query()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->active()])
            ->with([
                'children' => fn ($query) => $query->withCount([
                    'products' => fn ($inner) => $inner->active(),
                ]),
            ])
            ->orderBy('sort_order')
            ->get();

        // The same rule the footer reads, so the two cannot promise different
        // thresholds on one page.
        $freeShippingAmount = $shipping->freeShippingLabel();

        // The strip above the categories: only what is genuinely reduced, and
        // nothing at all when nothing is.
        $onSale = HomepageCatalog::onSale(10);

        $featured = HomepageCatalog::featured(10);

        // Only one product per root category is picked, so with 6 categories
        // "featured" tops out at 6 — fill the rest from the same pool "more"
        // draws from so the section still shows a full 10.
        if ($featured->count() < 10) {
            $featured = $featured->concat(
                HomepageCatalog::more($featured, 10 - $featured->count())
            );
        }
        $featured->load('discount');

        $more = HomepageCatalog::more($featured, 5);
        $more->load('discount');

        return view('home', [
            'freeShippingAmount' => $freeShippingAmount,
            'categories' => $categories,
            'onSale' => $onSale,
            'featured' => $featured,
            'more' => $more,
            // What customers said and what the shop wrote: the two things a
            // catalogue cannot say about itself.
            'testimonials' => $this->testimonials(),
            'reviewSummary' => $this->reviewSummary(),
            'catalogue' => $this->catalogueSize(),
            'readings' => $this->readings(),
            'marketplace' => MarketplaceSetting::current(),
        ]);
    }

    /**
     * The kindest recent word about what the shop sells.
     *
     * Five-star reviews only, newest first, each still pointing at the
     * product it judged: an unsourced testimonial is worth nothing, and
     * one that cannot be clicked reads like an invention.
     *
     * @return Collection<int, ProductReview>
     */
    private function testimonials(int $limit = 3): Collection
    {
        return ProductReview::query()
            ->where('rating', 5)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with(['product', 'user'])
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * How many references the shop carries, rounded down to a round figure.
     *
     * A product with variants is counted as its variants rather than as
     * itself: the parent is not something anyone can buy, and counting it
     * alongside its own sizes would inflate the claim. One with no variants
     * is a reference on its own.
     *
     * Rounded down so the number stays true between two stock takes: the
     * page says "plus de", and a figure that rounded up would not be.
     *
     * @return array{exact: int, rounded: int, rayons: int, categories: int}|null
     */
    private function catalogueSize(): ?array
    {
        $standalone = Product::query()
            ->where('is_active', true)
            ->whereDoesntHave('variants')
            ->count();

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->count();

        $exact = $standalone + $variants;
        $rounded = intdiv($exact, self::REFERENCE_STEP) * self::REFERENCE_STEP;

        // Below one step there is no round figure to claim, and "plus de 0"
        // is not a boast.
        if ($rounded < self::REFERENCE_STEP) {
            return null;
        }

        return [
            'exact' => $exact,
            'rounded' => $rounded,
            // Only shelves a visitor can actually reach. A rayon counts if
            // it holds active products itself or through one of its
            // subcategories, which is how the menu treats it too.
            'rayons' => Category::query()
                ->whereNull('parent_id')
                ->where(fn ($query) => $query
                    ->whereHas('products', fn ($inner) => $inner->active())
                    ->orWhereHas('children.products', fn ($inner) => $inner->active()))
                ->count(),
            'categories' => Category::query()
                ->whereNotNull('parent_id')
                ->whereHas('products', fn ($inner) => $inner->active())
                ->count(),
        ];
    }

    /**
     * What the whole shop scores, for the line beside the testimonials.
     *
     * Every review on a product a visitor can still open, which is the same
     * population the section's quotes are drawn from. A review stranded on
     * a deactivated product would count towards a figure nobody can check.
     *
     * @return array{average: float, count: int, fill: float}|null
     */
    private function reviewSummary(): ?array
    {
        $reviews = ProductReview::query()
            ->whereHas('product', fn ($query) => $query->where('is_active', true));

        $count = (clone $reviews)->count();

        if ($count === 0) {
            return null;
        }

        $average = round((float) $reviews->avg('rating'), 1);

        return [
            'average' => $average,
            'count' => $count,
            // The width of the painted part of the star row. Rounded with
            // the score rather than from the raw average, so the stars and
            // the number cannot disagree about the same rating.
            'fill' => round($average / 5 * 100, 1),
        ];
    }

    /**
     * The shop's own writing, linked from the page that gets the most
     * visits: two guides and the latest article. Until this, the whole
     * editorial cluster hung off one footer column.
     *
     * Which two guides is the day's business, not this method's: the shelf
     * holds more than the strip can show, and a guide that never comes up
     * is a guide nobody reads.
     *
     * Each card says what it is before saying what it is about: the kind of
     * writing, then the rayon it belongs to. « Guide » alone did not say
     * which shelf it advised on, and an article labelled « Conseils » alone
     * did not say it came from the blog.
     *
     * @return array<int, array{kind: string, topic: ?string, title: string, text: string, url: string, cta: string}>
     */
    private function readings(): array
    {
        $readings = Guides::ofTheDay()
            ->map(fn (array $guide): array => [
                'kind' => 'Guide',
                'topic' => $guide['topic'],
                'title' => $guide['title'],
                'text' => $guide['teaser'],
                'url' => $guide['url'],
                'cta' => 'Lire le guide',
            ])
            ->all();

        $post = BlogPost::query()->visible()->with('category')->orderByDesc('published_at')->first();

        if ($post !== null) {
            $readings[] = [
                'kind' => 'Blog',
                // Its rubric is the post's own; a category that no longer
                // resolves leaves the card saying « Blog » and no more.
                'topic' => $post->category?->localizedName(),
                'title' => $post->localizedTitle(),
                'text' => $post->localizedExcerpt(),
                'url' => route('blog.show', $post->slug),
                'cta' => __('store.blog_read'),
            ];
        }

        return $readings;
    }
}
