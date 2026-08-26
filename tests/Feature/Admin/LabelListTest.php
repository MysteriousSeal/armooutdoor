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

    /**
     * A product whose label is ready to print.
     *
     * The wording lives in its own row, so `title` and `subtitle` are passed
     * separately from the product's own columns; either can be nulled to make
     * the article unfinished.
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, string|null>  $wording
     */
    private function ready(array $overrides = [], array $wording = []): Product
    {
        return Product::factory()->labelled($wording)->create(array_merge([
            'name' => ['en' => 'Tactical gloves', 'fr' => 'Gants tactiques'],
            'sku' => 'ARM-GLOVE-M',
            'gtin' => '4006381333931',
        ], $overrides));
    }

    /** A product with no label wording at all. */
    private function unworded(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => ['en' => 'Tactical gloves', 'fr' => 'Gants tactiques'],
            'sku' => 'ARM-GLOVE-M',
            'gtin' => '4006381333931',
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
        $product = $this->ready(['gtin' => null], ['subtitle' => null]);

        $this->page()
            // Listed rather than hidden: the fields that would switch the
            // button on are on the line below it.
            ->assertSee('is-disabled', false)
            ->assertDontSee('Missing:')
            // The cell knows its own address so the script can switch the
            // button on after a save; what must not be there is the link.
            ->assertDontSee('<a href="'.route('admin.products.label', $product).'"', false);
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
        $link = fn (ProductVariant $variant): string => '<a href="'
            .route('admin.products.variants.label', ['product' => $product, 'variant' => $variant]).'"';

        $this->assertStringContainsString($link($medium), $html);
        $this->assertStringNotContainsString($link($large), $html);
        $this->assertStringContainsString('is-disabled', $html);
    }

    public function test_the_tabs_split_what_can_be_printed_from_what_cannot(): void
    {
        $this->ready(['sku' => 'ARM-OK']);
        $this->unworded(['sku' => 'ARM-KO', 'gtin' => '5901234123457']);

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
        $this->ready(['sku' => 'ARM-FOUND'], ['title' => 'Gants Woodland']);
        $this->ready(['sku' => 'ARM-OTHER', 'gtin' => '5901234123457'], ['title' => 'Cibles rondes']);

        $this->page(['search' => 'ARM-FOUND'])->assertSee('ARM-FOUND')->assertDontSee('ARM-OTHER');
        $this->page(['search' => 'Woodland'])->assertSee('ARM-FOUND')->assertDontSee('ARM-OTHER');
    }

    public function test_the_search_survives_a_tab(): void
    {
        $this->ready(['sku' => 'ARM-OK']);
        $this->unworded(['sku' => 'ARM-KO', 'gtin' => '5901234123457']);

        // Both filters hold at once, rather than one clearing the other.
        $this->page(['tab' => 'incomplete', 'search' => 'ARM-KO'])
            ->assertSee('ARM-KO')
            ->assertDontSee('ARM-OK');
    }

    public function test_an_empty_result_says_which_emptiness_it_is(): void
    {
        // One sentence per situation: a search that found nothing does not
        // read like a catalogue with nothing in it.
        $this->page(['search' => 'rien-du-tout'])->assertSee('Nothing matches');
        $this->page()->assertSee('No product to label yet.');

        $this->ready();
        $this->page(['tab' => 'incomplete'])->assertSee('Every article has its wording and its codes.');
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
        $product = $this->unworded();
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

        $label = $product->refresh()->label;
        // Stored in capitals, whatever the case it was typed in.
        $this->assertSame('GANTS TACTIQUES M-PACT', $label->title);
        $this->assertSame('TAILLE M', $label->subtitle);
        $this->assertSame('60 % polyester', $label->composition);
        $this->assertNull($label->mention);
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
        // Said under the size's own name rather than in a row of its own.
        $this->assertStringContainsString('Wording shared', $html);
        $this->assertStringNotContainsString('colspan="6"><span class="label-shared"', $html);
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
        $this->ready();

        // The wording is on the form below each row, so a column repeating it
        // would say the same thing twice.
        $this->page()->assertDontSee('>Label title</th>', false);
    }

    public function test_the_counts_speak_for_the_whole_catalogue(): void
    {
        // More products than fit on a page: a count that stopped at the page
        // size would only ever report the page size.
        Product::factory()->count(45)->create(['sku' => null, 'gtin' => null]);
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
        Product::factory()->count(45)->create(['sku' => null, 'gtin' => null]);
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
        $this->ready(['sku' => 'ARM-CODED'], ['subtitle' => null]);

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

    public function test_a_long_product_name_is_cut_by_the_browser_not_the_server(): void
    {
        $long = 'Cibles rondes réactives fluorescentes 10 cm numérotées, lot de cent';
        $this->ready(['name' => ['en' => $long, 'fr' => $long]]);

        // Two lines then the ellipsis, so the whole name stays in the markup
        // and in the tooltip rather than being lost to a truncation.
        $this->page()
            ->assertSee('admin-name-clamp', false)
            ->assertSee('title="'.e($long).'"', false)
            // The whole name, not a truncation: the ellipsis on the page is
            // the search placeholder's, so the name itself is what is checked.
            ->assertSee($long);
    }

    public function test_the_wording_lives_in_its_own_row(): void
    {
        $product = $this->unworded();

        $this->save($product, ['label_title' => 'Gants', 'label_subtitle' => 'Taille M']);

        // One row per product, whatever its number of sizes.
        $this->assertDatabaseCount('product_labels', 1);
        $this->assertDatabaseHas('product_labels', [
            'product_id' => $product->id,
            'title' => 'GANTS',
            'subtitle' => 'TAILLE M',
        ]);
    }

    public function test_saving_again_writes_to_the_same_row(): void
    {
        $product = $this->ready();

        $this->save($product, ['label_title' => 'Gants Woodland', 'label_subtitle' => 'Taille L']);

        $this->assertDatabaseCount('product_labels', 1);
        $this->assertSame('GANTS WOODLAND', $product->fresh()->label->title);
    }

    public function test_emptying_every_field_removes_the_row(): void
    {
        $product = $this->ready();

        $this->save($product, [
            'label_title' => '',
            'label_subtitle' => '',
            'label_composition' => '',
            'label_mention' => '',
        ]);

        // The row's existence is what "this product has wording" means, so an
        // emptied label is deleted rather than kept as four nulls.
        $this->assertDatabaseCount('product_labels', 0);
        $this->assertNull($product->fresh()->label);
    }

    public function test_deleting_a_product_takes_its_wording_with_it(): void
    {
        $product = $this->ready();

        $product->delete();

        $this->assertDatabaseCount('product_labels', 0);
    }

    /** Posts the label form for a product, as the owner. */
    private function save(Product $product, array $wording): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/labels/'.$product->id, $wording);
    }

    public function test_a_save_from_the_page_answers_in_place(): void
    {
        $product = $this->unworded();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->putJson('/admin/labels/'.$product->id, [
                'label_title' => 'Gants',
                'label_subtitle' => 'Taille M',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Label wording saved.');

        // The article can be printed now, so the answer says so and its button
        // stops claiming otherwise without a reload. A plain product answers
        // with an empty name, the way its cell identifies itself.
        $response->assertJsonPath('printable', ['']);
    }

    public function test_the_answer_names_only_the_sizes_that_can_be_printed(): void
    {
        $product = $this->unworded(['sku' => null, 'gtin' => null]);
        $medium = $this->variant($product, 'M', 'ARM-TS-M', '4006381333931');
        $large = $this->variant($product, 'L', 'ARM-TS-L', null);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson('/admin/labels/'.$product->id, [
                'label_title' => 'T-shirt',
                'label_subtitle' => 'Respirant',
            ])
            ->assertOk()
            ->assertJsonPath('printable', [(string) $medium->id]);

        $this->assertNotContains((string) $large->id, [(string) $medium->id]);
    }

    public function test_a_refused_save_comes_back_as_errors(): void
    {
        $product = $this->ready();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson('/admin/labels/'.$product->id, ['label_title' => str_repeat('a', 121)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('label_title');
    }

    public function test_the_page_carries_what_the_script_needs(): void
    {
        $product = $this->ready(['gtin' => null]);

        $this->page()
            ->assertSee('js/admin-label-save.js', false)
            ->assertSee('data-label-form', false)
            // The cell knows its article and where its sheet lives, so a save
            // can switch the button on.
            ->assertSee('data-label-action', false)
            ->assertSee('data-url="'.route('admin.products.label', $product).'"', false);
    }

    public function test_the_name_and_the_line_under_it_are_stored_in_capitals(): void
    {
        $product = $this->unworded();

        $this->save($product, [
            'label_title' => 'Gants tactiques M-Pact',
            'label_subtitle' => 'Taille M — woodland',
            'label_composition' => '60 % polyester',
            'label_mention' => 'Ne convient pas aux enfants',
        ]);

        $label = $product->fresh()->label;

        // Whoever types it, the label reads the same.
        $this->assertSame('GANTS TACTIQUES M-PACT', $label->title);
        $this->assertSame('TAILLE M — WOODLAND', $label->subtitle);
        // The other two are sentences, and keep their case.
        $this->assertSame('60 % polyester', $label->composition);
        $this->assertSame('Ne convient pas aux enfants', $label->mention);
    }

    public function test_the_capitals_keep_their_accents(): void
    {
        $product = $this->unworded();

        // A byte-by-byte upper would turn "é" into something unprintable.
        $this->save($product, [
            'label_title' => 'Cibles réactives fluo',
            'label_subtitle' => 'Lot de cent — numérotées',
        ]);

        $label = $product->fresh()->label;

        $this->assertSame('CIBLES RÉACTIVES FLUO', $label->title);
        $this->assertSame('LOT DE CENT — NUMÉROTÉES', $label->subtitle);
    }

    public function test_the_field_shows_what_will_be_stored(): void
    {
        $this->ready();

        // Typed in lowercase, shown in capitals: the field and the sheet agree
        // before the save rather than after it.
        $this->page()->assertSee('is-uppercase', false);
    }

    public function test_a_row_says_where_it_stands(): void
    {
        $this->ready(['sku' => 'ARM-OK']);
        $this->unworded(['sku' => 'ARM-KO', 'gtin' => '5901234123457']);

        $html = $this->page()->getContent();

        // Status then action: the row reads without the button's state having
        // to be interpreted.
        $this->assertMatchesRegularExpression('#label-status is-ready.*?Ready#s', $html);
        $this->assertMatchesRegularExpression('#label-status is-incomplete.*?Incomplete#s', $html);
    }

    public function test_the_chips_are_dressed_for_both_themes(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        // The green and the amber the admin already uses, and their dark
        // counterparts: a chip must not vanish when the theme flips.
        $this->assertMatchesRegularExpression('/\.label-status\.is-ready\s*\{[^}]*#3d6b4e/s', $css);
        $this->assertMatchesRegularExpression('/\.label-status\.is-incomplete\s*\{[^}]*#8a6d1f/s', $css);
        $this->assertMatchesRegularExpression("/\[data-theme='dark'\]\s*\.label-status\.is-ready/s", $css);
        $this->assertMatchesRegularExpression("/\[data-theme='dark'\]\s*\.label-status\.is-incomplete/s", $css);
    }

    public function test_the_fields_are_labelled_and_laid_out_on_two_lines(): void
    {
        $this->ready();

        $html = $this->page()->getContent();

        // From the form itself: the search field above the table wears the
        // same class, and counting the page would count that too.
        $form = substr($html, strpos($html, 'label-form" data-label-form'));
        $form = substr($form, 0, strpos($form, '</form>'));

        // A label above each field rather than a placeholder doing that job,
        // so a filled-in field still says what it is.
        $this->assertSame(4, substr_count($form, 'admin-field-label'));
        $this->assertSame(2, substr_count($html, 'label-form-field--wide'));
        $this->assertStringContainsString('label-form-actions', $html);
        $this->assertStringNotContainsString('class="sr-only" for="label-title', $html);
    }

    public function test_a_product_and_its_form_read_as_one_block(): void
    {
        $this->ready();

        $this->page()
            ->assertSee('label-row', false)
            ->assertSee('has-form', false)
            ->assertSee('label-form-row', false);
    }

    public function test_the_columns_stay_readable_down_a_long_page(): void
    {
        $this->ready();

        $css = (string) file_get_contents(public_path('css/admin.css'));

        $this->page()->assertSee('admin-labels-table', false);
        $this->assertMatchesRegularExpression('/\.admin-labels-table thead th\s*\{[^}]*position:\s*sticky/s', $css);
    }
}
