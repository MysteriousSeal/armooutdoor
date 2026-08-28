<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
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
        ]);
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
