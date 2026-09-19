<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Support\Octopia\Exporter;
use App\Support\Octopia\Readiness;
use App\Support\Octopia\TemplateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Cdiscount, through Octopia's product templates.
 *
 * Octopia takes no feed: a seller fills the Excel template of the category
 * and uploads it in their back office. So the shop keeps the templates, says
 * which of its products belong to each, and hands back the same file with
 * its own rows written in.
 */
class OctopiaController extends Controller
{
    private const DIRECTORY = 'octopia';

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

    public function index(Request $request): View
    {
        $templates = OctopiaTemplate::query()->orderBy('name')->get();
        $template = $templates->firstWhere('id', (int) $request->query('template')) ?? $templates->first();
        $lines = [];

        if ($template !== null) {
            $lines = (new Exporter($template))->lines($this->listings($template));
        }

        return view('admin.marketplaces.cdiscount', [
            'templates' => $templates,
            'template' => $template,
            'lines' => $lines,
            // Octopia refuses a picture that is not served over https, and
            // nothing here can fix that for a shop served over http.
            'imagesAreSecure' => str_starts_with((string) config('app.url'), 'https://'),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $request->validate([
            'template' => ['required', 'file', 'max:10240', 'mimetypes:application/vnd.ms-excel.sheet.macroEnabled.12,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
        ], [], ['template' => 'template file']);

        $file = $request->file('template');
        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'xlsm');

        Storage::disk('local')->putFileAs(
            self::DIRECTORY,
            $file,
            basename($path),
        );

        try {
            $read = (new TemplateFile(storage_path('app/private/'.$path)))->read();
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['template' => $e->getMessage()]);
        }

        // One template per category: a newer file for a category replaces the
        // one held, products and answers staying where they are.
        $existing = OctopiaTemplate::query()->where('code', $read['code'])->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($existing->path);
        }

        OctopiaTemplate::query()->updateOrCreate(['code' => $read['code']], [
            'name' => $read['name'] !== '' ? $read['name'] : $read['code'],
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'sheet_path' => $read['sheet_path'],
            'first_data_row' => TemplateFile::FIRST_DATA_ROW,
            'fields' => $read['fields'],
        ]);

        return back()->with('status', 'Template "'.$read['name'].'" saved, '.count($read['fields']).' columns read.');
    }

    public function destroyTemplate(OctopiaTemplate $template): RedirectResponse
    {
        Storage::disk('local')->delete($template->path);
        $template->delete();

        return redirect()->route('admin.marketplaces.cdiscount')
            ->with('status', 'Template removed. The products it described keep their answers.');
    }

    /**
     * A product's Cdiscount listing: the category that describes it, and the
     * answers that category asks for.
     *
     * A page of its own rather than a panel on the product form, as Vinted
     * has: what a marketplace asks is long, particular to it, and beside the
     * point when one is only correcting a price.
     */
    public function product(Product $product): View
    {
        $product->load(['variants', 'cdiscountListing.variants', 'cdiscountListing.template']);

        return view('admin.products.cdiscount', [
            'product' => $product,
            'listing' => $product->cdiscountListing,
            'templates' => OctopiaTemplate::query()->orderBy('name')->get(),
            'checks' => Readiness::checks($product),
        ]);
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

    /**
     * The template handed back with the chosen lines written into it.
     *
     * The file is Octopia's own, copied and added to: their macros and their
     * closed lists survive, which they would not through a spreadsheet
     * library rewriting the workbook.
     */
    public function export(Request $request, OctopiaTemplate $template): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*' => ['string'],
        ]);

        $exporter = new Exporter($template);
        $rows = $exporter->rows($exporter->lines($this->listings($template)), $data['lines']);

        if ($rows === []) {
            return back()->withErrors(['lines' => 'None of the chosen lines could be written.']);
        }

        $destination = tempnam(sys_get_temp_dir(), 'octopia').'.xlsm';

        try {
            $template->file()->fill($template->sheet_path, $rows, $destination);
        } catch (Throwable $e) {
            @unlink($destination);

            return back()->withErrors(['lines' => $e->getMessage()]);
        }

        $name = Str::slug($template->name).'-'.now()->format('Ymd-Hi').'.xlsm';

        return response()->download($destination, $name)->deleteFileAfterSend();
    }
}
