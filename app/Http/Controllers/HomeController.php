<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\ProductReview;
use App\Models\ShippingSetting;
use App\Support\Guides;
use App\Support\HomepageCatalog;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
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

        $freeShippingAmount = null;
        if ($thresholdCents !== null && $thresholdCents > 0) {
            $euros = $thresholdCents / 100;
            $freeShippingAmount = fmod($euros, 1.0) === 0.0
                ? number_format($euros, 0, ',', ' ').'€'
                : format_euros($thresholdCents);
        }

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
