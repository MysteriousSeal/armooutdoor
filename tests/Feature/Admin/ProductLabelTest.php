<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A printable label for one article. */
class ProductLabelTest extends TestCase
{
    use RefreshDatabase;

    /** A variant of a product, with its own codes. */
    private function variant(Product $product, ?string $sku, ?string $gtin): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => $sku,
            'gtin' => $gtin,
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_a_product_with_both_codes_prints_a_label(): void
    {
        $product = Product::factory()->labelled(['title' => 'Tente 2 places', 'subtitle' => 'Verte'])->create(['sku' => 'ARM-TENT-2P-GRN', 'gtin' => '4006381333931']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('label-arm-tent-2p-grn.pdf');
    }

    public function test_the_label_carries_what_a_package_must_say(): void
    {
        $product = Product::factory()->labelled(['title' => 'Tente 2 places', 'subtitle' => 'Verte'])->create(['sku' => 'ARM-TENT-2P-GRN', 'gtin' => '4006381333931']);

        $html = view('admin.products.label-pdf', [
            'title' => $product->label?->title,
            'subtitle' => $product->label?->subtitle,
            'composition' => $product->label?->composition,
            'mention' => $product->label?->mention,
            'sku' => $product->sku,
            'gtin' => Ean13::normalise($product->gtin),
            'modules' => Ean13::modules($product->gtin),
            'batchDate' => now()->format('d/m/Y'),
        ])->render();

        $this->assertStringContainsString('ARM-TENT-2P-GRN', $html);
        $this->assertStringContainsString('4006381333931', $html);
        $this->assertStringContainsString('SwiftShelf', $html);
        $this->assertStringContainsString('22 rue Anita Conti', $html);
        $this->assertStringContainsString('44300 Nantes, FR', $html);
        $this->assertStringContainsString('hello@swiftshelf.fr', $html);
        $this->assertStringContainsString('Made in PRC', $html);
        // The batch is the day it was printed, not anything stored.
        $this->assertStringContainsString(now()->format('d/m/Y'), $html);
    }

    public function test_the_barcode_is_drawn_as_bars(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-1', 'gtin' => '4006381333931']);

        $html = view('admin.products.label-pdf', [
            'title' => $product->label?->title,
            'subtitle' => $product->label?->subtitle,
            'composition' => $product->label?->composition,
            'mention' => $product->label?->mention,
            'sku' => $product->sku,
            'gtin' => Ean13::normalise($product->gtin),
            'modules' => Ean13::modules($product->gtin),
            'batchDate' => now()->format('d/m/Y'),
        ])->render();

        // 95 modules, each one cell, black or white.
        $this->assertSame(95, substr_count($html, '<td class="bar"') + substr_count($html, '<td class="space"'));
    }

    public function test_a_variant_prints_its_own_label(): void
    {
        $product = Product::factory()->labelled(['title' => 'T-shirt', 'subtitle' => 'Respirant'])->create(['sku' => null, 'gtin' => null]);
        $variant = $this->variant($product, 'ARM-TSHIRT-M', '5901234123457');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/variants/'.$variant->id.'/label')
            ->assertOk()
            ->assertDownload('label-arm-tshirt-m.pdf');
    }

    public function test_an_article_missing_either_code_has_no_label(): void
    {
        $admin = User::factory()->admin()->create();

        $noGtin = Product::factory()->create(['sku' => 'ARM-2', 'gtin' => null]);
        $noSku = Product::factory()->create(['sku' => null, 'gtin' => '4006381333931']);

        // A label without a reference names nothing; one without a barcode
        // cannot be scanned.
        $this->actingAs($admin)->get('/admin/products/'.$noGtin->id.'/label')->assertNotFound();
        $this->actingAs($admin)->get('/admin/products/'.$noSku->id.'/label')->assertNotFound();
    }

    public function test_a_variant_of_another_product_is_refused(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-3', 'gtin' => '4006381333931']);
        $other = Product::factory()->create(['sku' => null, 'gtin' => null]);
        $variant = $this->variant($other, 'ARM-OTHER-M', '5901234123457');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/variants/'.$variant->id.'/label')
            ->assertNotFound();
    }

    public function test_a_product_missing_its_wording_cannot_print_a_label(): void
    {
        $product = Product::factory()
            ->labelled(['title' => 'Gants', 'subtitle' => null])
            ->create(['sku' => 'ARM-13', 'gtin' => '4006381333931']);

        // The title and the subtitle are not needed to save a product, but a
        // label without them says nothing about the article.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertNotFound();
    }

    public function test_a_staff_admin_can_print_one(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-6', 'gtin' => '4006381333931']);

        // A label is a shipping job, not an accounting one.
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertOk();
    }

    public function test_a_customer_cannot_print_one(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-7', 'gtin' => '4006381333931']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertRedirect();
    }

    public function test_the_sheet_is_portrait(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-8', 'gtin' => '4006381333931']);

        $pdf = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertOk()
            ->getContent();

        preg_match('/MediaBox\s*\[[\d.\s]*?([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $box);

        $this->assertNotEmpty($box, 'No page size in the PDF.');
        // 500 × 700 CSS pixels, given to the renderer as points.
        $this->assertSame(375.0, (float) $box[1]);
        $this->assertSame(525.0, (float) $box[2]);
        $this->assertGreaterThan((float) $box[1], (float) $box[2]);
    }

    public function test_the_label_is_printed_across_the_sheet(): void
    {
        $product = Product::factory()->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])->create(['sku' => 'ARM-9', 'gtin' => '4006381333931']);

        $source = file_get_contents(resource_path('views/admin/products/label-pdf.blade.php'));

        // One quarter turn, on the reading face. The barcode is drawn flat and
        // ends up upright against the label's own reading direction; nesting a
        // second rotation inside the first is not something the renderer is
        // reliable about.
        $this->assertSame(1, substr_count($source, 'rotate('));
        $this->assertMatchesRegularExpression('/\.face\s*\{[^}]*transform:\s*rotate\(-90deg\)/s', $source);

        $pdf = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/label')
            ->assertOk()
            ->getContent();

        // The renderer honoured it: a rotation matrix reaches the page. Worth
        // reading the drawing itself, since dompdf ignores transforms it does
        // not understand and would simply lay the barcode down flat.
        $this->assertMatchesRegularExpression('/0\.000 1\.000 -1\.000 0\.000/', $this->contentStreams($pdf));
    }

    /** The drawing commands inside a PDF, inflated. */
    private function contentStreams(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        return collect($matches[1])
            ->map(fn (string $stream): string => (string) @gzuncompress($stream))
            ->join("\n");
    }

    /** The label's HTML for a product, as the controller builds it. */
    private function labelHtml(Product $product): string
    {
        return view('admin.products.label-pdf', [
            'title' => $product->label?->title,
            'subtitle' => $product->label?->subtitle,
            'composition' => $product->label?->composition,
            'mention' => $product->label?->mention,
            'sku' => $product->sku,
            'gtin' => Ean13::normalise($product->gtin),
            'modules' => Ean13::modules($product->gtin),
            'batchDate' => now()->format('d/m/Y'),
        ])->render();
    }

    public function test_the_wording_typed_on_the_product_reaches_its_label(): void
    {
        $product = Product::factory()
            ->labelled([
                'title' => 'Gants tactiques M-Pact',
                'subtitle' => 'Taille M — Woodland',
                'composition' => '60 % polyester, 40 % cuir de synthèse',
                'mention' => 'Ne convient pas aux enfants de moins de 14 ans',
            ])
            ->create(['sku' => 'ARM-10', 'gtin' => '4006381333931']);

        $html = $this->labelHtml($product);

        $this->assertStringContainsString('Gants tactiques M-Pact', $html);
        $this->assertStringContainsString('Taille M — Woodland', $html);
        $this->assertStringContainsString('Composition', $html);
        $this->assertStringContainsString('60 % polyester', $html);
        $this->assertStringContainsString('Ne convient pas aux enfants', $html);
    }

    public function test_the_name_is_read_before_the_reference(): void
    {
        $product = Product::factory()
            ->labelled(['title' => 'Gants tactiques M-Pact'])
            ->create(['sku' => 'ARM-11', 'gtin' => '4006381333931']);

        // From the body only: the reference also appears in the document's
        // title, which is not what is being ordered here.
        $html = $this->labelHtml($product);
        $body = substr($html, strpos($html, '<body>'));

        // A label is read as a product first, as a reference second.
        $this->assertLessThan(strpos($body, 'ARM-11'), strpos($body, 'Gants tactiques M-Pact'));
    }

    public function test_an_optional_field_left_empty_prints_nothing_at_all(): void
    {
        $product = Product::factory()
            ->labelled(['title' => 'Gants', 'subtitle' => 'Taille M'])
            ->create(['sku' => 'ARM-12', 'gtin' => '4006381333931']);

        $html = $this->labelHtml($product);

        // Not even the heading: a heading with nothing under it says less than
        // no heading at all.
        $this->assertStringNotContainsString('Composition', $html);
        $this->assertStringNotContainsString('class="mention"', $html);
        $this->assertStringContainsString('ARM-12', $html);
    }

    public function test_every_variant_of_a_product_says_the_same_thing(): void
    {
        $product = Product::factory()
            ->labelled(['title' => 'T-shirt respirant', 'subtitle' => 'Taille M'])
            ->create(['sku' => null, 'gtin' => null]);
        $medium = $this->variant($product, 'ARM-TS-M', '5901234123457');

        $pdf = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/variants/'.$medium->id.'/label')
            ->assertOk();

        // The wording lives on the product: only the reference and the barcode
        // change from one size to the next.
        $pdf->assertDownload('label-arm-ts-m.pdf');
        $this->assertSame('T-shirt respirant', $product->fresh()->label->title);
    }

    public function test_the_product_form_says_nothing_about_labels(): void
    {
        $product = Product::factory()
            ->labelled(['title' => 'Gants tactiques M-Pact', 'subtitle' => 'Taille M'])
            ->create(['sku' => 'ARM-14', 'gtin' => '4006381333931']);

        // Labels are edited from Catalog › Labels; the product form has no
        // section of its own for them.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertDontSee('name="label_title"', false)
            ->assertDontSee('Download label');
    }

    public function test_saving_a_product_leaves_its_label_wording_alone(): void
    {
        $product = Product::factory()
            ->labelled([
                'title' => 'Gants tactiques M-Pact',
                'subtitle' => 'Taille M',
                'composition' => '60 % polyester',
            ])
            ->create();

        // The product form never carried the fields, and saving it must not
        // reach into the label's own row either.
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/products/'.$product->id, [
                'name' => $product->localizedName(),
                'description' => '<p>Une description.</p>',
                'category_id' => $product->category_id,
                'price' => '19.90',
                'quantity' => 3,
            ])
            ->assertRedirect();

        $label = $product->refresh()->label;
        $this->assertSame('Gants tactiques M-Pact', $label->title);
        $this->assertSame('Taille M', $label->subtitle);
        $this->assertSame('60 % polyester', $label->composition);
    }
}
