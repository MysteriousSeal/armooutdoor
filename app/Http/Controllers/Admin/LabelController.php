<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Every article that could wear a label, and whether it can yet.
 *
 * An article is a product without variants, or one variant of a product that
 * has them: each has its own reference and barcode, and each is one printed
 * sheet. The page lists them one per row so it reads like the pile of labels
 * it produces.
 *
 * It doubles as the list of what still needs filling in, which is why an
 * article short of something appears here at all rather than being hidden.
 */
class LabelController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['ready', 'incomplete'], true)
            ? $request->query('tab')
            : 'all';

        $search = trim((string) $request->query('search'));

        // Products are paginated, not articles: a page of forty products may
        // print a hundred sheets, but the search and the paging have to hold
        // on to something the database can count. The tab narrows the query
        // too, so a page of the Ready tab is forty products that have
        // something ready rather than forty products of which three do.
        $products = Product::query()
            ->with('variants')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('label_title', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', '%'.$search.'%'));
            }))
            ->when($tab !== 'all', fn (Builder $query) => $this->holdingAn($query, $tab))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $articles = $this->articlesOf($products->getCollection());

        return view('admin.labels.index', [
            'products' => $products,
            'articles' => $this->onTab($articles, $tab),
            'tab' => $tab,
            'search' => $search,
            // Counted across the catalogue, not across the page: a count that
            // stopped at forty would only ever report the page size.
            'readyCount' => $this->countArticles(true),
            'incompleteCount' => $this->countArticles(false),
        ]);
    }

    /**
     * Narrows to products holding at least one article of the tab's kind.
     *
     * A product can be ready in one size and short in another, so it belongs
     * on both tabs; the view then shows only the rows that match.
     *
     * @param  Builder<Product>  $query
     */
    private function holdingAn(Builder $query, string $tab): Builder
    {
        if ($tab === 'ready') {
            return $query
                ->tap(fn (Builder $inner) => $this->hasWording($inner))
                ->where(fn (Builder $article) => $article
                    ->where(fn (Builder $plain) => $plain
                        ->whereDoesntHave('variants')
                        ->tap(fn (Builder $codes) => $this->hasCodes($codes)))
                    ->orWhereHas('variants', fn (Builder $variant) => $this->hasCodes($variant)));
        }

        // Short of its wording, every article of the product is unfinished;
        // otherwise it is the articles missing a code that put it here.
        return $query->where(fn (Builder $incomplete) => $incomplete
            ->whereNot(fn (Builder $wording) => $this->hasWording($wording))
            ->orWhere(fn (Builder $plain) => $plain
                ->whereDoesntHave('variants')
                ->whereNot(fn (Builder $codes) => $this->hasCodes($codes)))
            ->orWhereHas('variants', fn (Builder $variant) => $variant
                ->whereNot(fn (Builder $codes) => $this->hasCodes($codes))));
    }

    /**
     * How many articles of the catalogue are ready, or are not.
     *
     * Counted with two queries rather than by walking every product: the page
     * shows forty at a time and the tabs speak for all of them.
     */
    private function countArticles(bool $ready): int
    {
        $readyPlain = Product::query()
            ->whereDoesntHave('variants')
            ->tap(fn (Builder $query) => $this->hasWording($query))
            ->tap(fn (Builder $query) => $this->hasCodes($query))
            ->count();

        $readyVariants = ProductVariant::query()
            ->tap(fn (Builder $query) => $this->hasCodes($query))
            ->whereHas('product', fn (Builder $product) => $this->hasWording($product))
            ->count();

        if ($ready) {
            return $readyPlain + $readyVariants;
        }

        // Everything that is not ready: one article per plain product, one per
        // variant, less the ones already counted.
        return Product::query()->whereDoesntHave('variants')->count()
            + ProductVariant::query()->count()
            - $readyPlain
            - $readyVariants;
    }

    /**
     * A product whose label wording is filled in.
     *
     * Empty strings count as missing, the way `filled()` reads them.
     *
     * @param  Builder<Product>  $query
     */
    private function hasWording(Builder $query): Builder
    {
        return $query
            ->whereNotNull('label_title')->where('label_title', '!=', '')
            ->whereNotNull('label_subtitle')->where('label_subtitle', '!=', '');
    }

    /**
     * An article carrying both of its codes.
     *
     * @param  Builder<Product>|Builder<ProductVariant>  $query
     */
    private function hasCodes(Builder $query): Builder
    {
        return $query
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('gtin')->where('gtin', '!=', '');
    }

    /**
     * Saves the wording of one product's label.
     *
     * Comes back to the same page, tab and search: the list is worked through
     * a line at a time, and losing your place after each save would make that
     * unbearable.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'label_title' => ['nullable', 'string', 'max:120'],
            'label_subtitle' => ['nullable', 'string', 'max:120'],
            'label_composition' => ['nullable', 'string', 'max:500'],
            'label_mention' => ['nullable', 'string', 'max:500'],
        ]);

        $product->update(collect($validated)
            ->map(fn (?string $value): ?string => filled($value) ? trim($value) : null)
            ->all());

        return redirect()
            ->to($request->input('back', route('admin.labels.index')))
            ->with('status', 'Label wording saved.');
    }

    /**
     * Flattens products into the articles they print.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function articlesOf(Collection $products): Collection
    {
        return $products->flatMap(function (Product $product): array {
            if (! $product->hasVariants()) {
                return [$this->article($product, null)];
            }

            return $product->variants
                ->sortBy(fn (ProductVariant $variant) => $variant->sizeSortRank() ?? $variant->sort_order)
                ->values()
                ->map(fn (ProductVariant $variant, int $index): array => $this->article($product, $variant, $index === 0))
                ->all();
        });
    }

    /** @return array<string, mixed> */
    private function article(Product $product, ?ProductVariant $variant, bool $first = true): array
    {
        return [
            'product' => $product,
            'variant' => $variant,
            'name' => $variant?->label() ?: null,
            'sku' => ($variant ?? $product)->sku,
            'gtin' => ($variant ?? $product)->gtin,
            // The wording belongs to the product, so it is edited once: two
            // rows of one product carrying the same form would overwrite each
            // other without either saying so.
            'editable' => $first,
            'missing' => $product->labelRequirements($variant),
            'url' => $variant === null
                ? route('admin.products.label', $product)
                : route('admin.products.variants.label', ['product' => $product, 'variant' => $variant]),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $articles
     * @return Collection<int, array<string, mixed>>
     */
    private function onTab(Collection $articles, string $tab): Collection
    {
        return match ($tab) {
            'ready' => $articles->where('missing', [])->values(),
            'incomplete' => $articles->where('missing', '!=', [])->values(),
            default => $articles,
        };
    }
}
