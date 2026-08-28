<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): View
    {
        $rating = (int) $request->query('rating');
        if ($rating < 1 || $rating > 5) {
            $rating = 0;
        }

        $search = trim((string) $request->query('search'));

        $reviews = ProductReview::query()
            ->with(['product', 'user'])
            ->when($rating > 0, fn (Builder $query) => $query->where('rating', $rating))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->whereHas('product', fn (Builder $product) => $product
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Counted across every review, not across the page or the filter: the
        // tiles describe the shop, the list below describes the selection.
        $total = ProductReview::query()->count();

        $distribution = ProductReview::query()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'rating' => $rating,
            'search' => $search,
            'total' => $total,
            'average' => $total > 0 ? round((float) ProductReview::query()->avg('rating'), 1) : null,
            'ratingCounts' => collect(range(5, 1))
                ->mapWithKeys(fn (int $stars): array => [$stars => (int) ($distribution[$stars] ?? 0)]),
            'products' => $this->searchableProducts(),
        ]);
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
     * A review typed in by hand — one posted on a marketplace the shop sells
     * through, which the product page here should carry too. No customer, no
     * order: only the name the marketplace showed.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'author_name' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:50'],
            'posted_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

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
