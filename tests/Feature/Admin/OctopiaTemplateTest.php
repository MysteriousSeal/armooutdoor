<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Octopia\TemplateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Cdiscount, through Octopia's per-category product templates.
 *
 * Octopia takes no feed: it takes its own Excel file, filled in. So what has
 * to hold is that the file is read for what it asks, that a line says what it
 * still lacks rather than being refused on import, and above all that the
 * file handed back is Octopia's own: the export writes rows into a copy and
 * touches nothing else, macros included.
 */
class OctopiaTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** A template shaped like Octopia's: labels in row 4, codes in row 5. */
    private function templateFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'octopia').'.xlsm';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="CAGOULE" sheetId="1" r:id="rId1"/>'
            .'<sheet name="DataValidation" sheetId="2" state="hidden" r:id="rId2"/>'
            .'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Target="worksheets/sheet2.xml"/>'
            .'</Relationships>');

        $rows = [
            1 => ['A' => 'OCTOPIA', 'B' => 'Catégorie', 'C' => '0U0O05', 'D' => 'CAGOULE TECHNIQUE'],
            4 => ['A' => 'GTIN (EAN, ISBN, UPC…)*', 'B' => 'Référence vendeur*', 'C' => 'Titre*', 'D' => 'Description*', 'E' => 'URL image 1*', 'F' => 'Couleur(s)*', 'G' => 'Taille*', 'H' => 'Marque'],
            5 => ['A' => 'gtin', 'B' => 'sellerProductReference', 'C' => 'title', 'D' => 'description', 'E' => 'sellerPictureUrls_1', 'F' => '3263', 'G' => '46831', 'H' => 'brand'],
            6 => ['A' => 'Merci de ne pas modifier ces lignes', 'F' => 'mono', 'G' => 'monoranged'],
            8 => ['C' => '132 caractères max', 'G' => 'Liste bornée'],
        ];

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($rows));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheet([
            1 => ['A' => '46831'],
            2 => ['A' => 'Taille unique'],
            3 => ['A' => 'M'],
        ]));

        // The macros Octopia ships with the file: the export must hand them back.
        $zip->addFromString('xl/vbaProject.bin', 'MACROS-AS-THEY-CAME');
        $zip->close();

        return $path;
    }

    /** @param array<int, array<string, string>> $rows */
    private function sheet(array $rows): string
    {
        $xml = '';

        foreach ($rows as $number => $cells) {
            $xml .= '<row r="'.$number.'">';

            foreach ($cells as $column => $value) {
                $xml .= '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $perVariant
     */
    private function listing(Product $product, OctopiaTemplate $template, array $values = [], array $perVariant = []): CdiscountListing
    {
        return CdiscountListing::query()->create([
            'product_id' => $product->id,
            'octopia_template_id' => $template->id,
            'values' => $values,
            'per_variant' => $perVariant,
        ]);
    }

    private function upload(): OctopiaTemplate
    {
        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/templates', [
                'template' => new UploadedFile($this->templateFile(), 'pdt_template.xlsm', null, null, true),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return OctopiaTemplate::query()->firstOrFail();
    }

    public function test_a_template_is_read_for_its_category_and_its_columns(): void
    {
        $template = $this->upload();

        $this->assertSame('0U0O05', $template->code);
        $this->assertSame('CAGOULE TECHNIQUE', $template->name);
        $this->assertSame('xl/worksheets/sheet1.xml', $template->sheet_path);
        $this->assertSame(TemplateFile::FIRST_DATA_ROW, $template->first_data_row);
        $this->assertCount(8, $template->fields);

        $size = collect($template->fields)->firstWhere('code', '46831');
        $this->assertSame('Taille', $size['label']);
        $this->assertTrue($size['required']);
        // The closed list comes from the hidden sheet.
        $this->assertSame(['Taille unique', 'M'], $size['options']);

        // The catalogue fills these; only the category's own are asked of the admin.
        $this->assertSame(['Couleur(s)', 'Taille'], array_column($template->attributeFields(), 'label'));
    }

    public function test_a_file_that_is_not_a_template_is_refused(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/templates', [
                'template' => UploadedFile::fake()->create('notes.xlsm', 4),
            ])
            ->assertSessionHasErrors('template');

        $this->assertSame(0, OctopiaTemplate::query()->count());
    }

    public function test_the_page_names_what_a_line_still_lacks(): void
    {
        $template = $this->upload();

        $ready = Product::factory()->create([
            'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava'],
            'sku' => 'CAG-DESERT',
            'gtin' => '3760452700039',
        ]);
        $this->listing($ready, $template, ['3263' => 'Beige', '46831' => 'Taille unique']);

        $incomplete = Product::factory()->create([
            'name' => ['fr' => 'Cagoule forêt', 'en' => 'Forest balaclava'],
            'sku' => 'CAG-FORET',
            'gtin' => null,
        ]);
        $this->listing($incomplete, $template);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount')
            ->assertOk()
            ->assertSee('CAGOULE TECHNIQUE')
            ->assertSee($ready->localizedName())
            ->assertSee('Ready')
            ->assertSee($incomplete->localizedName())
            // Named one by one, rather than a refusal from Octopia days later.
            ->assertSee('GTIN (EAN, ISBN, UPC…), Couleur(s), Taille');
    }

    public function test_the_export_writes_the_rows_into_octopia_s_own_file(): void
    {
        $template = $this->upload();

        $product = Product::factory()->create([
            'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava'],
            'sku' => 'CAG-DESERT',
            'gtin' => '3760452700039',
            'brand' => 'Armo',
            'image' => 'products/cagoule.webp',
            'description' => ['fr' => '<p>Une cagoule <strong>respirante</strong>.</p>', 'en' => '<p>A balaclava.</p>'],
        ]);
        $listing = $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'Taille unique'], ['46831']);

        $small = ProductVariant::create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'CAG-DESERT-M',
            'gtin' => '3760452700046',
            'quantity' => 2,
        ]);
        $listing->variants()->create(['product_variant_id' => $small->id, 'values' => ['46831' => 'M']]);
        $unique = ProductVariant::create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Unique']],
            'sku' => 'CAG-DESERT-U',
            'gtin' => '3760452700053',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/templates/'.$template->id.'/export', [
                'lines' => [$product->id.':'.$small->id, $product->id.':'.$unique->id],
            ])
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=cagoule-technique-'.now()->format('Ymd-Hi').'.xlsm');

        $path = tempnam(sys_get_temp_dir(), 'exported').'.xlsm';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        // Octopia's own file: the macros are the ones it shipped.
        $this->assertSame('MACROS-AS-THEY-CAME', $zip->getFromName('xl/vbaProject.bin'));

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        // The template's own rows are untouched, and ours start at row 9.
        $this->assertStringContainsString('<c r="C4"', $sheet);
        $this->assertStringContainsString('<c r="A9" t="inlineStr"><is><t xml:space="preserve">3760452700046</t></is></c>', $sheet);
        $this->assertStringContainsString('CAG-DESERT-M', $sheet);
        $this->assertStringContainsString('<c r="A10" t="inlineStr"><is><t xml:space="preserve">3760452700053</t></is></c>', $sheet);

        // The variant's own answer wins; the product's stands where it says nothing.
        $this->assertStringContainsString('<c r="G9" t="inlineStr"><is><t xml:space="preserve">M</t></is></c>', $sheet);
        $this->assertStringContainsString('<c r="G10" t="inlineStr"><is><t xml:space="preserve">Taille unique</t></is></c>', $sheet);
        // The colour is the product's, on both lines.
        $this->assertStringContainsString('<c r="F9" t="inlineStr"><is><t xml:space="preserve">Beige</t></is></c>', $sheet);
        // The description goes in as plain text: the template refuses HTML.
        $this->assertStringContainsString('Une cagoule respirante.', $sheet);
        $this->assertStringNotContainsString('&lt;strong&gt;', $sheet);
    }

    public function test_the_exported_file_name_never_passes_forty_characters(): void
    {
        $template = $this->upload();
        $template->update(['name' => 'Bonnet technique - cagoule technique - tube de sport']);
        $product = Product::factory()->create(['gtin' => '3760452700039']);
        $this->listing($product, $template);

        $response = $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/templates/'.$template->id.'/export', ['lines' => [$product->id.':0']])
            ->assertOk();

        preg_match('/filename=(\S+)/', $response->headers->get('content-disposition'), $match);

        $this->assertLessThanOrEqual(40, strlen($match[1]));
        $this->assertStringEndsWith(now()->format('Ymd-Hi').'.xlsm', $match[1]);
        $this->assertStringStartsWith('bonnet-technique-cago-', $match[1]);
    }

    public function test_the_listing_has_a_page_of_its_own_asking_the_category_s_attributes(): void
    {
        $template = $this->upload();
        $product = Product::factory()->create();
        $this->listing($product, $template);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/cdiscount')
            ->assertOk()
            ->assertSee('Octopia category')
            ->assertSee('name="values[46831]"', false)
            ->assertSee('Taille unique');
    }

    /** The product page carries the link, with what is still missing on it. */
    public function test_the_product_page_links_to_the_listing(): void
    {
        $template = $this->upload();
        $product = Product::factory()->create(['gtin' => null]);
        $this->listing($product, $template);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Cdiscount listing')
            ->assertSee('/products/'.$product->id.'/cdiscount', false)
            // A category but no EAN and no answers: one of the three marks.
            ->assertSee('1/3')
            // What the listing asks is not asked twice on the product form.
            ->assertDontSee('name="values[46831]"', false);
    }

    public function test_the_listing_page_saves_the_answers_of_the_product_and_of_its_variants(): void
    {
        $template = $this->upload();
        $product = Product::factory()->create();
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CAG-M', 'quantity' => 1]);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'values' => ['3263' => 'Beige', '46831' => ''],
                'per_variant' => ['46831'],
                'variants' => [$variant->id => ['46831' => 'M']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $listing = $product->fresh()->cdiscountListing;

        $this->assertSame($template->id, $listing->octopia_template_id);
        // An empty answer is not stored: the export reports it missing instead.
        $this->assertSame(['3263' => 'Beige'], $listing->answers());
        $this->assertSame(['46831'], $listing->perVariantCodes());
        $this->assertSame(['46831' => 'M'], $listing->variants->firstWhere('product_variant_id', $variant->id)->answers());
        // The variant's own answer stands over the product's.
        $this->assertSame('M', $listing->answersFor($variant->fresh())['46831']);
    }

    /** No category means the product is not sold there. */
    public function test_clearing_the_category_removes_the_listing(): void
    {
        $template = $this->upload();
        $product = Product::factory()->create();
        $this->listing($product, $template, ['3263' => 'Beige']);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', ['octopia_template_id' => ''])
            ->assertRedirect();

        $this->assertNull($product->fresh()->cdiscountListing);
        // The product itself is untouched.
        $this->assertNotNull($product->fresh());
    }

    public function test_removing_a_template_takes_its_listings_with_it(): void
    {
        $template = $this->upload();
        $product = Product::factory()->create();
        $this->listing($product, $template, ['3263' => 'Beige']);

        $this->actingAs($this->admin())
            ->delete('/admin/marketplaces/cdiscount/templates/'.$template->id)
            ->assertRedirect();

        $this->assertSame(0, OctopiaTemplate::query()->count());
        // The answers go with the category they answered; the product stays.
        $this->assertNull($product->fresh()->cdiscountListing);
        $this->assertNotNull($product->fresh());
    }

    public function test_a_customer_reaches_none_of_it(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/marketplaces/cdiscount')->assertRedirect();
        $this->actingAs($customer)->post('/admin/marketplaces/cdiscount/templates')->assertRedirect();
    }
}
