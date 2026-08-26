<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /**
     * The tabs, and what each one keeps.
     *
     * The four last ones single out one requirement each: a page of "missing
     * a barcode" is a list of what to go and find, which is a different job
     * from working through everything unfinished.
     */
    private const TABS = ['ready', 'incomplete', 'no-title', 'no-subtitle', 'no-sku', 'no-gtin'];

    /** Which requirement a tab is about, for the tabs that are about one. */
    private const TAB_REQUIREMENTS = [
        'no-title' => 'title',
        'no-subtitle' => 'subtitle',
        'no-sku' => 'reference',
        'no-gtin' => 'barcode',
    ];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true)
            ? $request->query('tab')
            : 'all';

        $search = trim((string) $request->query('search'));

        // Products are paginated, not articles: a page of forty products may
        // print a hundred sheets, but the search and the paging have to hold
        // on to something the database can count. The tab narrows the query
        // too, so a page of the Ready tab is forty products that have
        // something ready rather than forty products of which three do.
        $products = Product::query()
            // A product taken off the shop has nothing to label, and a page of
            // retired articles would say there is work to do where there is
            // none.
            ->active()
            ->with(['variants' => fn ($variants) => $variants->where('is_active', true), 'label'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('label', fn (Builder $label) => $label->where('title', 'like', '%'.$search.'%'))
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
            'missingCounts' => collect(self::TAB_REQUIREMENTS)
                ->map(fn (string $requirement): int => $this->countMissing($requirement)),
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

        // One requirement at a time: the wording belongs to the product, so a
        // product short of it puts all its articles on the tab; a code belongs
        // to the article, so only the articles missing it come.
        if (isset(self::TAB_REQUIREMENTS[$tab])) {
            $requirement = self::TAB_REQUIREMENTS[$tab];

            if (in_array($requirement, ['title', 'subtitle'], true)) {
                return $query->whereNot(fn (Builder $wording) => $this->hasWordingField($wording, $requirement));
            }

            $column = $requirement === 'reference' ? 'sku' : 'gtin';

            return $query->where(fn (Builder $article) => $article
                ->where(fn (Builder $plain) => $plain
                    ->whereDoesntHave('variants')
                    ->whereNot(fn (Builder $code) => $this->hasCode($code, $column)))
                ->orWhereHas('variants', fn (Builder $variant) => $variant
                    ->whereNot(fn (Builder $code) => $this->hasCode($code, $column))));
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
     * How many articles of the catalogue are short of one requirement.
     *
     * @param  'title'|'subtitle'|'reference'|'barcode'  $requirement
     */
    private function countMissing(string $requirement): int
    {
        if (in_array($requirement, ['title', 'subtitle'], true)) {
            // The wording is the product's, so every article of a product
            // short of it counts.
            $plain = $this->plainProducts()
                ->whereNot(fn (Builder $query) => $this->hasWordingField($query, $requirement))
                ->count();

            $variants = $this->sellableVariants()
                ->whereHas('product', fn (Builder $product) => $product
                    ->active()
                    ->whereNot(fn (Builder $query) => $this->hasWordingField($query, $requirement)))
                ->count();

            return $plain + $variants;
        }

        $column = $requirement === 'reference' ? 'sku' : 'gtin';

        return $this->plainProducts()
            ->whereNot(fn (Builder $query) => $this->hasCode($query, $column))
            ->count()
            + $this->sellableVariants()
                ->whereNot(fn (Builder $query) => $this->hasCode($query, $column))
                ->count();
    }

    /**
     * How many articles of the catalogue are ready, or are not.
     *
     * Counted with two queries rather than by walking every product: the page
     * shows forty at a time and the tabs speak for all of them.
     */
    private function countArticles(bool $ready): int
    {
        $readyPlain = $this->plainProducts()
            ->tap(fn (Builder $query) => $this->hasWording($query))
            ->tap(fn (Builder $query) => $this->hasCodes($query))
            ->count();

        $readyVariants = $this->sellableVariants()
            ->tap(fn (Builder $query) => $this->hasCodes($query))
            ->whereHas('product', fn (Builder $product) => $this->hasWording($product->active()))
            ->count();

        if ($ready) {
            return $readyPlain + $readyVariants;
        }

        // Everything that is not ready: one article per plain product, one per
        // variant, less the ones already counted.
        return $this->plainProducts()->count()
            + $this->sellableVariants()->count()
            - $readyPlain
            - $readyVariants;
    }

    /**
     * The products that are one article on their own.
     *
     * On sale and without sizes: a retired product needs no label, and one
     * with sizes is counted through them.
     *
     * @return Builder<Product>
     */
    private function plainProducts(): Builder
    {
        return Product::query()->active()->whereDoesntHave('variants');
    }

    /**
     * The sizes that are one article each.
     *
     * On sale, and belonging to a product that is: a size withdrawn from a
     * product still on the shop needs no label either.
     *
     * @return Builder<ProductVariant>
     */
    private function sellableVariants(): Builder
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->active());
    }

    /**
     * A product whose label wording is filled in.
     *
     * The wording lives in its own row, so a product with no row has none.
     * Empty strings count as missing, the way `filled()` reads them.
     *
     * @param  Builder<Product>  $query
     */
    private function hasWording(Builder $query): Builder
    {
        return $query
            ->tap(fn (Builder $inner) => $this->hasWordingField($inner, 'title'))
            ->tap(fn (Builder $inner) => $this->hasWordingField($inner, 'subtitle'));
    }

    /**
     * A product whose label carries one of its two words.
     *
     * @param  Builder<Product>  $query
     * @param  'title'|'subtitle'  $field
     */
    private function hasWordingField(Builder $query, string $field): Builder
    {
        return $query->whereHas('label', fn (Builder $label) => $label
            ->whereNotNull($field)->where($field, '!=', ''));
    }

    /**
     * An article carrying both of its codes.
     *
     * @param  Builder<Product>|Builder<ProductVariant>  $query
     */
    private function hasCodes(Builder $query): Builder
    {
        return $query
            ->tap(fn (Builder $inner) => $this->hasCode($inner, 'sku'))
            ->tap(fn (Builder $inner) => $this->hasCode($inner, 'gtin'));
    }

    /**
     * An article carrying one of its codes.
     *
     * @param  Builder<Product>|Builder<ProductVariant>  $query
     * @param  'sku'|'gtin'  $column
     */
    private function hasCode(Builder $query, string $column): Builder
    {
        return $query->whereNotNull($column)->where($column, '!=', '');
    }

    /**
     * Saves the wording of one product's label.
     *
     * Comes back to the same page, tab and search: the list is worked through
     * a line at a time, and losing your place after each save would make that
     * unbearable.
     */
    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'label_title' => ['nullable', 'string', 'max:120'],
            'label_subtitle' => ['nullable', 'string', 'max:120'],
            'label_composition' => ['nullable', 'string', 'max:500'],
            'label_mention' => ['nullable', 'string', 'max:500'],
        ]);

        $wording = collect($validated)
            ->mapWithKeys(function (?string $value, string $field): array {
                $name = str_replace('label_', '', $field);
                $value = filled($value) ? trim($value) : null;

                // The name and the line under it are printed in capitals, so
                // they are stored that way: the label then reads the same
                // whoever typed it. `Str::upper` handles the accents.
                if ($value !== null && in_array($name, ['title', 'subtitle'], true)) {
                    $value = Str::upper($value);
                }

                return [$name => $value];
            });

        // A label emptied of everything is deleted rather than kept as four
        // nulls: the row's existence is what "this product has wording" means.
        if ($wording->filter()->isEmpty()) {
            $product->label?->delete();
        } else {
            $product->label()->updateOrCreate([], $wording->all());
        }

        // Saved in place when the page can do it itself. The answer names the
        // articles that can be printed now, so their buttons stop lying
        // without a reload.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Label wording saved.',
                'printable' => $this->printableArticlesOf($product->fresh('label', 'variants')),
            ]);
        }

        return redirect()
            ->to($request->input('back', route('admin.labels.index')))
            ->with('status', 'Label wording saved.');
    }

    /**
     * Which of a product's articles can be printed as it stands.
     *
     * A variant is named by its id and a plain product by nothing at all, the
     * way the rows of the page identify themselves.
     *
     * @return array<int, string>
     */
    private function printableArticlesOf(Product $product): array
    {
        if (! $product->hasVariants()) {
            return $product->labelIsPrintable() ? [''] : [];
        }

        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => $product->labelIsPrintable($variant))
            ->map(fn (ProductVariant $variant): string => (string) $variant->id)
            ->values()
            ->all();
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
                ->where('is_active', true)
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
        if (isset(self::TAB_REQUIREMENTS[$tab])) {
            $requirement = self::TAB_REQUIREMENTS[$tab];

            return $articles
                ->filter(fn (array $article): bool => in_array($requirement, $article['missing'], true))
                ->values();
        }

        return match ($tab) {
            'ready' => $articles->where('missing', [])->values(),
            'incomplete' => $articles->where('missing', '!=', [])->values(),
            default => $articles,
        };
    }
}
