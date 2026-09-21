<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product's Cdiscount listing, against the category read from Octopia.
 *
 * What has to hold is that a product says which category describes it, that
 * the category's own attributes are asked and answered per product and per
 * variant, and that a line says what it still lacks rather than being refused
 * by Octopia days later.
 */
class OctopiaTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** A category as the API reads it: only its own attributes, no catalogue columns. */
    private function category(): OctopiaTemplate
    {
        return OctopiaTemplate::query()->create([
            'code' => '0U0O05',
            'name' => 'CAGOULE TECHNIQUE',
            'synced_at' => now(),
            'fields' => [
                ['column' => null, 'code' => '3263', 'label' => 'Couleur(s)', 'required' => true, 'kind' => 'multi', 'constraint' => '', 'options' => []],
                ['column' => null, 'code' => '46831', 'label' => 'Taille', 'required' => true, 'kind' => 'monoranged', 'constraint' => '', 'options' => ['Taille unique', 'M']],
                ['column' => null, 'code' => '9999', 'label' => 'Poids', 'required' => false, 'kind' => 'mono', 'constraint' => '', 'options' => []],
            ],
        ]);
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

    public function test_the_page_names_what_a_line_still_lacks(): void
    {
        $template = $this->category();

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
            ->assertSee('EAN, Couleur(s), Taille');
    }

    public function test_the_listing_has_a_page_of_its_own_asking_the_category_s_attributes(): void
    {
        $template = $this->category();
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
        $template = $this->category();
        $product = Product::factory()->create(['gtin' => null]);
        $this->listing($product, $template);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Cdiscount listing')
            ->assertSee('/products/'.$product->id.'/cdiscount', false)
            // A category and the default offer, but no EAN and no answers: two of the four marks.
            ->assertSee('2/4')
            // What the listing asks is not asked twice on the product form.
            ->assertDontSee('name="values[46831]"', false);
    }

    public function test_the_listing_page_saves_the_answers_of_the_product_and_of_its_variants(): void
    {
        $template = $this->category();
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
        $template = $this->category();
        $product = Product::factory()->create();
        $this->listing($product, $template, ['3263' => 'Beige']);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', ['octopia_template_id' => ''])
            ->assertRedirect();

        $this->assertNull($product->fresh()->cdiscountListing);
        // The product itself is untouched.
        $this->assertNotNull($product->fresh());
    }

    public function test_removing_a_category_takes_its_listings_with_it(): void
    {
        $template = $this->category();
        $product = Product::factory()->create();
        $this->listing($product, $template, ['3263' => 'Beige']);

        $this->actingAs($this->admin())
            ->delete('/admin/marketplaces/cdiscount/categories/'.$template->id)
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
        $this->actingAs($customer)->post('/admin/marketplaces/cdiscount/categories')->assertRedirect();
    }
}
