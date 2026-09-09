<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Every review customers have left on the shop, in one place.
 *
 * Reviews are written from the product pages and only ever read there, one
 * product at a time; this page is the other direction — the shop's whole
 * voice at once, and the place to remove a review that shouldn't stay.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 25;

    /** A hand-typed review whose marketplace nobody wrote down. */
    private const UNATTRIBUTED = 'Unattributed';

    /*
     * The two channels that are not a marketplace name, and the prefix that
     * keeps the ones that are out of their way: a shop selling through a
     * marketplace called « Direct » would otherwise filter to the wrong list.
     */
    private const DIRECT_KEY = 'direct';

    private const UNATTRIBUTED_KEY = 'unattributed';

    private const SOURCE_KEY = 'source:';

    public function index(Request $request): View
    {
        $rating = (int) $request->query('rating');
        if ($rating < 1 || $rating > 5) {
            $rating = 0;
        }

        $search = trim((string) $request->query('search'));

        $channels = $this->channels();

        // A channel read off the query string is only honoured while it still
        // has reviews in it: a bookmarked filter on a marketplace the shop
        // has since stopped selling through shows everything rather than an
        // empty page with no way back.
        $channel = trim((string) $request->query('channel'));
        if ($channel !== '' && ! $channels->contains(fn (array $known): bool => $known['key'] === $channel)) {
            $channel = '';
        }

        $reviews = ProductReview::query()
            ->with(['product', 'user'])
            ->when($rating > 0, fn (Builder $query) => $query->where('rating', $rating))
            ->when($channel !== '', fn (Builder $query) => $this->scopeToChannel($query, $channel))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->whereHas('product', fn (Builder $product) => $product
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'))
                    // A hand-typed review has no customer row to search, so
                    // searching for the name printed on it found nothing:
                    // the only reviews the shop types in itself were the
                    // only ones its own search could not reach. The
                    // marketplace is searchable for the same reason.
                    ->orWhere('author_name', 'like', '%'.$search.'%')
                    ->orWhere('source', 'like', '%'.$search.'%');
            }))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Counted across every review, not across the page or the filter: the
        // tiles describe the shop, the list below describes the selection.
        $total = ProductReview::query()->count();

        // How much of the catalogue has anything said about it at all. The
        // average answers « are they good »; this answers « how many products
        // are still silent », which is the one the shop can act on.
        $reviewedProducts = ProductReview::query()->distinct()->count('product_id');
        $productCount = Product::query()->count();

        $distribution = ProductReview::query()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'rating' => $rating,
            'search' => $search,
            'channel' => $channel,
            'channelLabel' => $channels->firstWhere('key', $channel)['label'] ?? null,
            'total' => $total,
            'average' => $total > 0 ? round((float) ProductReview::query()->avg('rating'), 2) : null,
            'reviewedProducts' => $reviewedProducts,
            'productCount' => $productCount,
            'ratingCounts' => collect(range(5, 1))
                ->mapWithKeys(fn (int $stars): array => [$stars => (int) ($distribution[$stars] ?? 0)]),
            'channels' => $channels,
            'products' => $this->searchableProducts(),
        ]);
    }

    /**
     * Where the shop's reviews came from, one line per channel.
     *
     * The shop sells in more than one place and carries the reviews back
     * here by hand, so « 42 reviews » was answering the wrong question: what
     * matters is how many of them the shop earned on its own pages and how
     * many it borrowed, marketplace by marketplace. A review a customer
     * posted here is direct; a hand-typed one belongs to the marketplace
     * named on it, or to nothing in particular when nobody said.
     *
     * @return Collection<int, array{key: string, label: string, total: int, direct: bool}>
     */
    private function channels(): Collection
    {
        $direct = ProductReview::query()->whereNotNull('user_id')->count();

        $borrowed = ProductReview::query()
            ->whereNull('user_id')
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        return collect([['key' => self::DIRECT_KEY, 'label' => 'Direct', 'total' => $direct, 'direct' => true]])
            ->concat($borrowed->map(fn (int $total, ?string $source): array => [
                // A grouped null key comes back as an empty string, and an
                // unnamed marketplace is still a channel: it just has no
                // name to print.
                'key' => filled($source) ? self::SOURCE_KEY.$source : self::UNATTRIBUTED_KEY,
                'label' => filled($source) ? $source : self::UNATTRIBUTED,
                'total' => $total,
                'direct' => false,
            ])->values())
            ->filter(fn (array $channel): bool => $channel['total'] > 0)
            // Heaviest first, and then by name rather than by whatever order
            // the database happened to group in: two channels level on count
            // would otherwise swap places between page loads. The unnamed
            // one sits last whatever it weighs, being a gap rather than a
            // channel anyone could go and look at.
            ->sort(fn (array $a, array $b): int => $b['total'] <=> $a['total']
                ?: ($a['label'] === self::UNATTRIBUTED ? 1 : 0) <=> ($b['label'] === self::UNATTRIBUTED ? 1 : 0)
                ?: strcasecmp($a['label'], $b['label']))
            ->values();
    }

    /**
     * Narrow the list to one channel.
     *
     * @param  Builder<ProductReview>  $query
     * @return Builder<ProductReview>
     */
    private function scopeToChannel(Builder $query, string $channel): Builder
    {
        if ($channel === self::DIRECT_KEY) {
            return $query->whereNotNull('user_id');
        }

        if ($channel === self::UNATTRIBUTED_KEY) {
            return $query->whereNull('user_id')->whereNull('source');
        }

        return $query
            ->whereNull('user_id')
            ->where('source', Str::after($channel, self::SOURCE_KEY));
    }

    /**
     * The whole catalogue for the add-review search, ordered the way the
     * dropdown lists it.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    private function searchableProducts()
    {
        $nameSql = Product::query()->getConnection()->getDriverName() === 'sqlite'
            ? "json_extract(name, '$.fr')"
            : "json_unquote(json_extract(name, '$.fr'))";

        return Product::query()->orderByRaw($nameSql)->get();
    }

    /**
     * What a hand-typed review has to say for itself, on the way in and on
     * the way back through the edit form. Written once: the two forms carry
     * the same fields, and a rule added to one belongs to both.
     *
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'author_name' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:50'],
            'posted_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * A review typed in by hand — one posted on a marketplace the shop sells
     * through, which the product page here should carry too. No customer, no
     * order: only the name the marketplace showed.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $review = ProductReview::query()->create([
            'product_id' => $validated['product_id'],
            'author_name' => trim($validated['author_name']),
            'rating' => $validated['rating'],
            'comment' => trim($validated['comment']),
            'source' => filled($validated['source'] ?? null) ? trim($validated['source']) : null,
        ]);

        // Dated when the marketplace published it, not when it was copied
        // over: the review sorts among the others as if posted here.
        if (filled($validated['posted_at'] ?? null)) {
            $review->created_at = $validated['posted_at'];
            $review->save();
        }

        AdminActivityLog::record('review.created', $review, 'Added a review of '.$review->product->localizedName().' by '.$review->author_name.($review->source ? ' from '.$review->source : ''));

        return redirect()
            ->route('admin.reviews.index')
            ->with('status', 'Review added.');
    }

    /**
     * Correcting a review the shop typed in itself.
     *
     * Copying a marketplace review over by hand goes wrong the ordinary ways
     * typing goes wrong: a name misspelt, a star short, the whole thing filed
     * against the wrong product. Until now the only repair was to delete it
     * and type it again, which also lost the date it was published on.
     *
     * Only the hand-typed ones. A customer's review is that customer's own
     * words, published under a name the shop reads back to them on the
     * product page: the back office has exactly one power over those, which
     * is to take them down.
     */
    public function update(Request $request, ProductReview $review): RedirectResponse
    {
        abort_unless($review->isManual(), 404);

        $validated = $request->validate($this->rules());

        $review->fill([
            'product_id' => $validated['product_id'],
            'author_name' => trim($validated['author_name']),
            'rating' => $validated['rating'],
            'comment' => trim($validated['comment']),
            'source' => filled($validated['source'] ?? null) ? trim($validated['source']) : null,
        ]);

        // An empty date field leaves the review where it already sorts. The
        // alternative — reading a blank as « today » — would quietly move a
        // two-year-old review to the top of the product page every time
        // somebody fixed a typo in it.
        if (filled($validated['posted_at'] ?? null)) {
            $review->created_at = $validated['posted_at'];
        }

        $review->save();

        AdminActivityLog::record('review.updated', $review, 'Edited a review of '.($review->product?->localizedName() ?? 'a deleted product').' by '.$review->author_name);

        // Back to the same page, filter and search the edit was launched
        // from, like deleting one.
        return redirect()
            ->to($request->input('back', route('admin.reviews.index')))
            ->with('status', 'Review updated.');
    }

    public function destroy(Request $request, ProductReview $review): RedirectResponse
    {
        $productName = $review->product?->localizedName() ?? 'a deleted product';
        $reviewer = $review->user?->name ?? 'a deleted customer';
        $review->delete();

        AdminActivityLog::record('review.deleted', null, 'Deleted a review of '.$productName.' by '.$reviewer);

        // Comes back to the same page, filter and search, like the labels
        // list: moderation is worked through a line at a time.
        return redirect()
            ->to($request->input('back', route('admin.reviews.index')))
            ->with('status', 'Review deleted.');
    }
}
