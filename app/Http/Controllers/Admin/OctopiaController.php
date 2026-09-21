<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CdiscountListing;
use App\Models\OctopiaSubmission;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Octopia\OctopiaAttributeWriter;
use App\Services\Octopia\OctopiaClient;
use App\Services\Octopia\OctopiaDescriptionWriter;
use App\Support\Octopia\ApiFields;
use App\Support\Octopia\Exporter;
use App\Support\Octopia\Readiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Cdiscount, through Octopia.
 *
 * The shop keeps what Octopia asks of each category, read from Octopia's API,
 * and says which of its products belong to each.
 */
class OctopiaController extends Controller
{
    /**
     * The listings of a category, with everything a line reads.
     *
     * @return Collection<int, CdiscountListing>
     */
    private function listings(OctopiaTemplate $template)
    {
        return $template->listings()
            ->with(['variants', 'product.variants', 'product.images'])
            ->get()
            ->sortBy(fn ($listing): string => (string) $listing->product?->localizedName())
            ->values();
    }

    public function index(Request $request, OctopiaClient $octopia): View
    {
        $templates = OctopiaTemplate::query()->orderBy('name')->get();
        $matches = [];
        $findError = null;

        try {
            $matches = $this->matchingCategories($octopia, trim((string) $request->query('find', '')));
        } catch (Throwable $e) {
            // Said on the page: « no match » would read as Octopia having
            // nothing, when it is the credentials or the network.
            $findError = $e->getMessage();
        }

        $template = $templates->firstWhere('id', (int) $request->query('template')) ?? $templates->first();
        $lines = [];

        if ($template !== null) {
            $lines = (new Exporter($template))->lines($this->listings($template));
        }

        return view('admin.marketplaces.cdiscount', [
            'templates' => $templates,
            'template' => $template,
            'lines' => $lines,
            'submissions' => $template?->submissions()->limit(10)->get() ?? collect(),
            'apiConfigured' => $octopia->isConfigured(),
            'find' => trim((string) $request->query('find', '')),
            'matches' => $matches,
            'findError' => $findError,
            // Octopia refuses a picture that is not served over https, and
            // nothing here can fix that for a shop served over http.
            'imagesAreSecure' => str_starts_with((string) config('app.url'), 'https://'),
        ]);
    }

    /**
     * A category read from Octopia's API: what it asks of a product. Reading
     * it again refreshes it, products and answers staying where they are.
     */
    public function importCategory(Request $request, OctopiaClient $octopia): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
        ], ['code.regex' => 'An Octopia category code is 6 letters or digits.']);

        try {
            $category = $octopia->category($data['code']);
            $fields = ApiFields::fromProperties($octopia->properties($data['code']));
        } catch (Throwable $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        if ($fields === []) {
            return back()->withErrors(['code' => 'Octopia lists no attribute for '.$category['label'].'.']);
        }

        OctopiaTemplate::query()->updateOrCreate(['code' => $category['code']], [
            'name' => $category['label'],
            'fields' => $fields,
            'is_variant' => $category['is_variant'],
            'synced_at' => now(),
        ]);

        return redirect()
            ->route('admin.marketplaces.cdiscount', ['template' => OctopiaTemplate::query()->where('code', $category['code'])->value('id')])
            ->with('status', '« '.$category['label'].' » read from Octopia, '.count($fields).' attributes.');
    }

    /**
     * The categories whose name or code holds what was typed. Octopia has no
     * search of its own, so this filters the list the client keeps.
     *
     * @return list<array{code: string, label: string}>
     *
     * @throws Throwable when Octopia cannot be read
     */
    private function matchingCategories(OctopiaClient $octopia, string $find): array
    {
        if ($find === '' || ! $octopia->isConfigured()) {
            return [];
        }

        $categories = $octopia->categories();

        // Accents and case set aside: « cagoule » finds « Cagoule ».
        $needle = Str::lower(Str::ascii($find));

        return collect($categories)
            ->filter(fn (array $category): bool => str_contains(Str::lower(Str::ascii($category['label'])), $needle)
                || str_contains(Str::lower($category['code']), $needle))
            ->take(30)
            ->values()
            ->all();
    }

    /**
     * The lines the request ticked, or the reason nothing can be sent.
     *
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    private function chosenLines(Request $request, OctopiaTemplate $template, OctopiaClient $octopia): array
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'max:100'],
            'lines.*' => ['string'],
        ], ['lines.required' => 'Tick at least one line to send.']);

        if (! $octopia->isConfigured()) {
            return [[], 'The Octopia credentials are not set on this environment.'];
        }

        // A category read before its kind was kept is asked about once, here:
        // a variant category refuses a product sheet without its group
        // reference, and the payload has to know.
        if ($template->is_variant === null) {
            try {
                $template->update(['is_variant' => $octopia->category($template->code)['is_variant']]);
            } catch (Throwable $e) {
                return [[], $e->getMessage()];
            }
        }

        $chosen = array_flip($data['lines']);
        $lines = array_values(array_filter(
            (new Exporter($template))->lines($this->listings($template)),
            fn (array $line): bool => isset($chosen[$line['key']]),
        ));

        return $lines === [] ? [[], 'None of the chosen lines belong to this category.'] : [$lines, null];
    }

    /**
     * Send the chosen lines to Octopia's catalogue.
     *
     * Only lines with nothing missing go, and only with https pictures:
     * Octopia refuses the rest, and a refused batch is a request spent for
     * nothing. This creates the product sheets; a price and a stock are an
     * offer, which is sent apart.
     */
    public function send(Request $request, OctopiaTemplate $template, OctopiaClient $octopia): RedirectResponse
    {
        [$lines, $problem] = $this->chosenLines($request, $template, $octopia);

        if ($problem !== null) {
            return back()->withErrors(['lines' => $problem]);
        }

        foreach ($lines as $line) {
            if ($line['missing'] !== []) {
                return back()->withErrors(['lines' => $line['title'].' still lacks: '.implode(', ', $line['missing']).'.']);
            }

            foreach ($line['payload']['sellerPictureUrls'] as $picture) {
                if (! str_starts_with($picture['url'], 'https://')) {
                    return back()->withErrors(['lines' => 'Octopia only takes pictures served over https, and '.$line['title'].' has '.$picture['url'].'. Send from the live site.']);
                }
            }
        }

        try {
            $packageId = $octopia->submitProducts(array_column($lines, 'payload'));
        } catch (Throwable $e) {
            return back()->withErrors(['lines' => $e->getMessage()]);
        }

        $this->recordSubmission($template, 'products', $packageId, $lines);

        return back()->with('status', count($lines).' '.Str::plural('product', count($lines)).' sent to Octopia. Check the result below in a minute.');
    }

    /**
     * Put the chosen lines on sale: their price, stock and delivery.
     *
     * The products have to be in Octopia's catalogue first, and integrated:
     * an offer on an EAN Octopia does not know is rejected, and the report
     * says so. What the offer needs from the seller is asked on the product's
     * own Cdiscount page.
     */
    public function sendOffers(Request $request, OctopiaTemplate $template, OctopiaClient $octopia): RedirectResponse
    {
        [$lines, $problem] = $this->chosenLines($request, $template, $octopia);

        if ($problem !== null) {
            return back()->withErrors(['lines' => $problem]);
        }

        foreach ($lines as $line) {
            if ($line['gtin'] === '') {
                return back()->withErrors(['lines' => $line['title'].' has no EAN: Octopia knows an offer by it.']);
            }

            if ($line['offer']['missing'] !== []) {
                return back()->withErrors(['lines' => 'The offer for '.$line['title'].' still lacks: '.implode(', ', $line['offer']['missing']).'. Set it on the product\'s Cdiscount page.']);
            }
        }

        try {
            $packageId = $octopia->submitOffers(array_column(array_column($lines, 'offer'), 'payload'));
        } catch (Throwable $e) {
            return back()->withErrors(['lines' => $e->getMessage()]);
        }

        $this->recordSubmission($template, 'offers', $packageId, $lines);

        return back()->with('status', count($lines).' '.Str::plural('offer', count($lines)).' sent to Octopia. Check the result below in a minute.');
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function recordSubmission(OctopiaTemplate $template, string $kind, string $packageId, array $lines): void
    {
        $template->submissions()->create([
            'kind' => $kind,
            'package_id' => $packageId,
            'lines' => array_map(fn (array $line): array => [
                'gtin' => $line['gtin'],
                'reference' => $line['reference'],
                'title' => $line['title'],
            ], $lines),
        ]);
    }

    /** Ask Octopia what became of a batch. */
    public function checkSubmission(OctopiaSubmission $submission, OctopiaClient $octopia): RedirectResponse
    {
        try {
            // An offer package has no results before Octopia has processed
            // it, and asking early is answered with an error: say where it
            // stands instead.
            if ($submission->isOffers()) {
                $state = $octopia->offerPackageState($submission->package_id);

                if ($state !== null && ! in_array($state, ['Integrated', 'Rejected'], true)) {
                    return back()->withErrors(['submission' => 'Octopia is still processing this package (it is '.$state.'). Check again in a minute.']);
                }
            }

            $report = $submission->isOffers()
                ? $octopia->offerResults($submission->package_id)
                : $octopia->productReports($submission->package_id);
        } catch (Throwable $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        $submission->update(['report' => $report, 'checked_at' => now()]);

        return back();
    }

    public function destroyCategory(OctopiaTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.marketplaces.cdiscount')
            ->with('status', 'Category removed. The answers given for it go with it; the products stay.');
    }

    /**
     * A product's Cdiscount listing: the category that describes it, and the
     * answers that category asks for.
     *
     * A page of its own rather than a panel on the product form, as Vinted
     * has: what a marketplace asks is long, particular to it, and beside the
     * point when one is only correcting a price.
     */
    public function product(Product $product, OctopiaAttributeWriter $writer): View
    {
        $product->load(['variants', 'cdiscountListing.variants', 'cdiscountListing.template']);

        return view('admin.products.cdiscount', [
            'product' => $product,
            'listing' => $product->cdiscountListing,
            'templates' => OctopiaTemplate::query()->orderBy('name')->get(),
            'checks' => Readiness::checks($product),
            // No key on this environment, no button: one that answers "not
            // configured" every time is worse than one that is not there.
            'canGenerate' => $writer->isConfigured(),
            'offer' => ($product->cdiscountListing ?? new CdiscountListing)->offerSettings(),
            // What each line is sold at in the shop, to show what Cdiscount will charge.
            'priceLines' => $this->priceLines($product),
        ]);
    }

    /**
     * Claude fills the category's attributes from what the product sheet says.
     *
     * The answer is not saved: it lands in the form like something typed
     * there, and Save decides whether it stays. The fields already answered
     * are sent along so they are neither asked again nor replaced.
     */
    public function generateAttributes(Request $request, Product $product, OctopiaAttributeWriter $writer): JsonResponse
    {
        if (! $writer->isConfigured()) {
            return response()->json(['message' => 'No Anthropic API key is configured on this environment.'], 503);
        }

        $data = $request->validate([
            'octopia_template_id' => ['required', 'integer', 'exists:octopia_templates,id'],
            'per_variant' => ['nullable', 'array'],
            'per_variant.*' => ['string', 'max:64'],
            'skip' => ['nullable', 'array'],
            'skip.*' => ['string', 'max:64'],
        ]);

        $template = OctopiaTemplate::query()->findOrFail((int) $data['octopia_template_id']);

        try {
            return response()->json($writer->fill(
                $product->load(['category', 'variants']),
                $template,
                array_values($data['per_variant'] ?? []),
                array_values($data['skip'] ?? []),
            ));
        } catch (RuntimeException $e) {
            // Said out loud rather than logged alone: somebody is waiting on
            // the button, and an empty form would read as an empty answer.
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * Claude writes the title and the description this product is sent to
     * Cdiscount with.
     *
     * Not saved: they land in the fields, and Save decides whether they stay.
     * They are written for the category chosen, which is why it is sent.
     */
    public function generateDescription(Request $request, Product $product, OctopiaDescriptionWriter $writer): JsonResponse
    {
        if (! $writer->isConfigured()) {
            return response()->json(['message' => 'No Anthropic API key is configured on this environment.'], 503);
        }

        $data = $request->validate([
            'octopia_template_id' => ['required', 'integer', 'exists:octopia_templates,id'],
        ]);

        try {
            return response()->json($writer->write(
                $product->load(['category', 'variants']),
                OctopiaTemplate::query()->findOrFail((int) $data['octopia_template_id']),
            ));
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'octopia_template_id' => ['nullable', 'integer', 'exists:octopia_templates,id'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],
            'per_variant' => ['nullable', 'array'],
            'per_variant.*' => ['string', 'max:64'],
            'variants' => ['nullable', 'array'],
            'variants.*' => ['nullable', 'array'],
            'variants.*.*' => ['nullable', 'string', 'max:5000'],
            'title' => ['nullable', 'string', 'max:'.OctopiaDescriptionWriter::TITLE_LIMIT],
            'description' => ['nullable', 'string', 'max:'.OctopiaDescriptionWriter::LIMIT],
            'offer' => ['nullable', 'array'],
            'offer.condition' => ['nullable', 'string', Rule::in(array_keys(CdiscountListing::CONDITIONS))],
            'offer.markup' => ['nullable', 'numeric', 'min:-50', 'max:200'],
            'offer.preparation_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'offer.delivery' => ['nullable', 'array'],
            'offer.delivery.*.enabled' => ['nullable', 'boolean'],
            'offer.delivery.*.cost' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'offer.delivery.*.additional' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        // No category means the product is not sold there: the listing goes
        // rather than lingering as an answer to nothing.
        if (blank($data['octopia_template_id'] ?? null)) {
            $product->cdiscountListing?->delete();

            return redirect()->route('admin.products.cdiscount.edit', $product)
                ->with('status', 'Cdiscount listing removed.');
        }

        $listing = CdiscountListing::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'octopia_template_id' => (int) $data['octopia_template_id'],
                'values' => $this->answers((array) ($data['values'] ?? [])),
                'per_variant' => array_values(array_unique(array_map('strval', (array) ($data['per_variant'] ?? [])))),
                'offer' => $this->offer((array) ($data['offer'] ?? [])),
                // Empty is none of its own: the shop's name and description stand in.
                'title' => filled($data['title'] ?? null) ? trim($data['title']) : null,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            ],
        );

        $variantIds = $product->variants->pluck('id')->all();

        foreach ((array) ($data['variants'] ?? []) as $variantId => $values) {
            if (! in_array((int) $variantId, $variantIds, true)) {
                continue;
            }

            $answers = $this->answers((array) $values);

            if ($answers === []) {
                $listing->variants()->where('product_variant_id', (int) $variantId)->delete();

                continue;
            }

            $listing->variants()->updateOrCreate(
                ['product_variant_id' => (int) $variantId],
                ['values' => $answers],
            );
        }

        return redirect()->route('admin.products.cdiscount.edit', $product)
            ->with('status', 'Cdiscount listing saved.');
    }

    /**
     * The lines the product is sold as, each with the shop's price: one for a
     * product on its own, one per active variant otherwise.
     *
     * @return list<array{label: string, cents: int}>
     */
    private function priceLines(Product $product): array
    {
        $variants = $product->variants->where('is_active', true);

        if ($variants->isEmpty()) {
            return [['label' => $product->localizedName(), 'cents' => $product->effectivePriceCents()]];
        }

        return $variants->map(fn (ProductVariant $variant): array => [
            'label' => $variant->label() !== '' ? $variant->label() : 'Variant',
            'cents' => $variant->effectivePriceCents(),
        ])->values()->all();
    }

    /**
     * The offer settings as they are kept: a delivery mode counts only when it
     * is ticked and has a cost, and a blank number is no number rather than a
     * zero (a free delivery is a cost of 0, typed).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function offer(array $input): array
    {
        $delivery = [];

        foreach (array_keys(CdiscountListing::DELIVERY_MODES) as $code) {
            $mode = (array) ($input['delivery'][$code] ?? []);

            if (! empty($mode['enabled']) && ($mode['cost'] ?? '') !== '') {
                $delivery[$code] = [
                    'cost' => round((float) $mode['cost'], 2),
                    'additional' => ($mode['additional'] ?? '') !== '' ? round((float) $mode['additional'], 2) : null,
                ];
            }
        }

        return [
            'condition' => $input['condition'] ?? 'New',
            'markup' => ($input['markup'] ?? '') !== '' ? (float) $input['markup'] : 0.0,
            'preparation_days' => ($input['preparation_days'] ?? '') !== '' ? (int) $input['preparation_days'] : null,
            'delivery' => $delivery,
        ];
    }

    /**
     * Answers with something in them. An empty one is not stored: an
     * attribute nobody filled in is one the export reports missing.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function answers(array $values): array
    {
        $answers = [];

        foreach ($values as $code => $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $answers[(string) $code] = $value;
            }
        }

        return $answers;
    }
}
