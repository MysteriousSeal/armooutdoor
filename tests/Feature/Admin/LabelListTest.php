<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The list of every article that could wear a label. */
class LabelListTest extends TestCase
{
    use RefreshDatabase;

    /** A product whose label is ready to print. */
    private function ready(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => ['en' => 'Tactical gloves', 'fr' => 'Gants tactiques'],
            'sku' => 'ARM-GLOVE-M',
            'gtin' => '4006381333931',
            'label_title' => 'Gants tactiques M-Pact',
            'label_subtitle' => 'Taille M',
        ], $overrides));
    }

    /** A variant of a product, with its own codes. */
    private function variant(Product $product, string $label, ?string $sku, ?string $gtin): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $label, 'fr' => $label],
            'attribute_values' => [['label' => 'Size', 'value' => $label]],
            'sku' => $sku,
            'gtin' => $gtin,
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /** Opens the list as an admin. */
    private function page(array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/labels'.($query === [] ? '' : '?'.http_build_query($query)))
            ->assertOk();
    }

    public function test_the_page_lists_a_product_with_its_thumbnail_reference_and_title(): void
    {
        $product = $this->ready();

        $this->page()
            ->assertSee('Gants tactiques')
            ->assertSee('ARM-GLOVE-M')
            ->assertSee('4006381333931')
            ->assertSee('admin-product-thumb', false)
            ->assertSee('/admin/products/'.$product->id.'/label', false);
    }

    public function test_an_article_short_of_something_is_listed_with_its_button_off(): void
    {
        $product = $this->ready(['label_subtitle' => null, 'gtin' => null]);

        $this->page()
            // Listed rather than hidden: the fields that would switch the
            // button on are on the line below it.
            ->assertSee('is-disabled', false)
            ->assertDontSee('Missing:')
            ->assertDontSee('/admin/products/'.$product->id.'/label', false);
    }

    public function test_a_product_with_variants_gives_one_row_per_size(): void
    {
        $product = $this->ready(['sku' => null, 'gtin' => null]);
        $medium = $this->variant($product, 'M', 'ARM-TS-M', '4006381333931');
        $large = $this->variant($product, 'L', 'ARM-TS-L', null);

        $html = $this->page()->getContent();

        // One row per printed sheet: each size has its own reference and its
        // own barcode.
        $this->assertStringContainsString('ARM-TS-M', $html);
        $this->assertStringContainsString('ARM-TS-L', $html);
        $this->assertStringContainsString('/admin/products/'.$product->id.'/variants/'.$medium->id.'/label', $html);
        $this->assertStringNotContainsString('/admin/products/'.$product->id.'/variants/'.$large->id.'/label', $html);
        $this->assertStringContainsString('is-disabled', $html);
    }

    public function test_the_tabs_split_what_can_be_printed_from_what_cannot(): void
    {
        $this->ready(['sku' => 'ARM-OK']);
        $this->ready(['sku' => 'ARM-KO', 'gtin' => '5901234123457', 'label_title' => null]);

        $this->page(['tab' => 'ready'])
            ->assertSee('ARM-OK')
            ->assertDontSee('ARM-KO');

        $this->page(['tab' => 'incomplete'])
            ->assertSee('ARM-KO')
            ->assertDontSee('ARM-OK');

        $this->page()->assertSee('ARM-OK')->assertSee('ARM-KO');
    }

    public function test_the_search_finds_a_product_by_reference_or_label_title(): void
    {
        $this->ready(['sku' => 'ARM-FOUND', 'label_title' => 'Gants Woodland']);
        $this->ready(['sku' => 'ARM-OTHER', 'label_title' => 'Cibles rondes', 'gtin' => '5901234123457']);

        $this->page(['search' => 'ARM-FOUND'])->assertSee('ARM-FOUND')->assertDontSee('ARM-OTHER');
        $this->page(['search' => 'Woodland'])->assertSee('ARM-FOUND')->assertDontSee('ARM-OTHER');
    }

    public function test_the_search_survives_a_tab(): void
    {
        $this->ready(['sku' => 'ARM-OK']);
        $this->ready(['sku' => 'ARM-KO', 'label_title' => null, 'gtin' => '5901234123457']);

        // Both filters hold at once, rather than one clearing the other.
        $this->page(['tab' => 'incomplete', 'search' => 'ARM-KO'])
            ->assertSee('ARM-KO')
            ->assertDontSee('ARM-OK');
    }

    public function test_an_empty_result_says_so(): void
    {
        $this->page(['search' => 'rien-du-tout'])->assertSee('No article to show here.');
    }

    public function test_the_page_hangs_under_the_catalog_menu(): void
    {
        $this->page()
            ->assertSee('>Catalog', false)
            ->assertSee('/admin/labels', false)
            ->assertDontSee('>Catalogue', false);
    }

    public function test_a_staff_admin_can_reach_it(): void
    {
        // Printing labels is a shipping job, not an owner's one.
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/labels')
            ->assertOk();
    }

    public function test_a_customer_cannot(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/labels')->assertRedirect();
    }

    public function test_the_row_shows_the_variant_and_the_barcode(): void
    {
        $product = $this->ready(['sku' => null, 'gtin' => null]);
        $this->variant($product, 'M', 'ARM-TS-M', '4006381333931');

        $html = $this->page()
            ->assertSee('>Variant</th>', false)
            ->assertSee('>GTIN</th>', false)
            ->assertSee('ARM-TS-M')
            ->assertSee('4006381333931')
            ->getContent();

        // The variant's own name, in its own column.
        $this->assertMatchesRegularExpression('#<td>\s*M\s*</td>#', $html);
    }

    public function test_the_wording_can_be_edited_from_the_list(): void
    {
        $product = $this->ready();

        $this->page()
            ->assertSee('name="label_title"', false)
            ->assertSee('name="label_subtitle"', false)
            ->assertSee('name="label_composition"', false)
            ->assertSee('name="label_mention"', false)
            ->assertSee('/admin/labels/'.$product->id, false);
    }

    public function test_saving_the_wording_comes_back_where_you_were(): void
    {
        $product = $this->ready(['label_title' => null, 'label_subtitle' => null]);
        $back = '/admin/labels?tab=incomplete&search=ARM';

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/labels/'.$product->id, [
                'label_title' => 'Gants tactiques M-Pact',
                'label_subtitle' => 'Taille M',
                'label_composition' => '60 % polyester',
                'label_mention' => '',
                'back' => $back,
            ])
            // The list is worked through a line at a time; losing your place
            // after each save would make that unbearable.
            ->assertRedirect($back)
            ->assertSessionHas('status');

        $product->refresh();
        $this->assertSame('Gants tactiques M-Pact', $product->label_title);
        $this->assertSame('Taille M', $product->label_subtitle);
        $this->assertSame('60 % polyester', $product->label_composition);
        $this->assertNull($product->label_mention);
    }

    public function test_only_the_first_size_carries_the_form(): void
    {
        $product = $this->ready(['sku' => null, 'gtin' => null]);
        $this->variant($product, 'M', 'ARM-TS-M', '4006381333931');
        $this->variant($product, 'L', 'ARM-TS-L', '5901234123457');

        $html = $this->page()->getContent();

        // One form per set of fields: two rows of one product editing the same
        // wording would overwrite each other without either saying so.
        $this->assertSame(1, substr_count($html, 'name="label_title"'));
        $this->assertStringContainsString('Wording shared with the sizes above.', $html);
    }

    public function test_a_wording_too_long_is_refused(): void
    {
        $product = $this->ready();

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/labels/'.$product->id, [
                'label_title' => str_repeat('a', 121),
            ])
            ->assertSessionHasErrors('label_title');
    }

    public function test_the_list_carries_no_label_title_column(): void
    {
        $this->ready(['label_title' => 'Gants tactiques M-Pact']);

        // The wording is on the form below each row, so a column repeating it
        // would say the same thing twice.
        $this->page()->assertDontSee('>Label title</th>', false);
    }

    public function test_the_counts_speak_for_the_whole_catalogue(): void
    {
        // More products than fit on a page: a count that stopped at the page
        // size would only ever report the page size.
        Product::factory()->count(45)->create(['sku' => null, 'gtin' => null, 'label_title' => null]);
        $this->ready(['sku' => 'ARM-OK-1']);
        $this->ready(['sku' => 'ARM-OK-2', 'gtin' => '5901234123457']);

        $html = $this->page()->getContent();

        $ready = substr($html, strpos($html, 'Ready'));
        $incomplete = substr($html, strpos($html, 'Incomplete'));

        $this->assertMatchesRegularExpression('#Ready.*?admin-tab-count">2<#s', $ready);
        $this->assertMatchesRegularExpression('#Incomplete.*?admin-tab-count">45<#s', $incomplete);
    }

    public function test_a_tab_pages_through_its_own_articles(): void
    {
        Product::factory()->count(45)->create(['sku' => null, 'gtin' => null, 'label_title' => null]);
        $this->ready(['sku' => 'ARM-OK-1']);

        // The one ready article is on the first page of its tab, not buried
        // behind forty-five unfinished ones.
        $this->page(['tab' => 'ready'])->assertSee('ARM-OK-1');
    }

    public function test_a_product_ready_in_one_size_appears_on_both_tabs(): void
    {
        $product = $this->ready(['sku' => null, 'gtin' => null]);
        $this->variant($product, 'M', 'ARM-TS-M', '4006381333931');
        $this->variant($product, 'L', 'ARM-TS-L', null);

        // The tab lists articles, so each side shows only its own rows.
        $this->page(['tab' => 'ready'])->assertSee('ARM-TS-M')->assertDontSee('ARM-TS-L');
        $this->page(['tab' => 'incomplete'])->assertSee('ARM-TS-L')->assertDontSee('ARM-TS-M');
    }

    public function test_a_product_short_of_its_wording_is_incomplete_whatever_its_codes(): void
    {
        $this->ready(['sku' => 'ARM-CODED', 'label_subtitle' => null]);

        $this->page(['tab' => 'incomplete'])->assertSee('ARM-CODED');
        $this->page(['tab' => 'ready'])->assertDontSee('ARM-CODED');
    }

    public function test_the_reference_copies_on_a_click(): void
    {
        $this->ready(['sku' => 'ARM-GLOVE-M']);

        // The same mechanism as an order's lines: a real button, the value on
        // a data attribute, and the script that puts it on the clipboard.
        $this->page()
            ->assertSee('data-copy-code="ARM-GLOVE-M"', false)
            ->assertSee('order-item-sku-copy', false)
            ->assertSee('aria-label="Copy SKU ARM-GLOVE-M"', false)
            ->assertSee('js/admin-copy-code.js', false);
    }

    public function test_an_article_without_a_reference_has_nothing_to_copy(): void
    {
        $this->ready(['sku' => null]);

        $this->page()->assertDontSee('data-copy-code', false);
    }
}
