<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Support\OrganizationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is selling, and what a category page holds.
 *
 * The catalogue declared its products and its articles but never the business
 * behind them, and the category pages — the ones that matter commercially —
 * carried no structured data at all while the listings around them did.
 */
class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function company(): CompanySetting
    {
        return tap(CompanySetting::current())->update([
            'company_name' => 'Armo Outdoor SAS',
            'address' => '22 Rue Anita Conti, 44300, Nantes',
            'contact_email' => 'contact@armooutdoor.fr',
            'phone' => '0961167966',
            'siret' => '10254195000014',
        ]);
    }

    public function test_the_home_page_says_who_runs_the_shop(): void
    {
        $schema = OrganizationSchema::for($this->company());

        $this->assertSame('OnlineStore', $schema['@type']);
        $this->assertSame('Armo Outdoor SAS', $schema['legalName']);
        $this->assertSame('contact@armooutdoor.fr', $schema['email']);
        $this->assertSame('10254195000014', $schema['taxID']);
        $this->assertSame('EUR', $schema['currenciesAccepted']);
    }

    public function test_the_registered_address_is_split_into_its_parts(): void
    {
        $address = OrganizationSchema::for($this->company())['address'];

        $this->assertSame('22 Rue Anita Conti', $address['streetAddress']);
        $this->assertSame('44300', $address['postalCode']);
        $this->assertSame('Nantes', $address['addressLocality']);
        $this->assertSame('FR', $address['addressCountry']);
    }

    public function test_a_field_the_company_never_filled_in_is_left_out(): void
    {
        // The legal pages show "[Numéro de TVA]" so a human sees what is
        // missing; publishing that as a VAT number would state something
        // untrue about the business.
        $schema = OrganizationSchema::for($this->company());

        $this->assertArrayNotHasKey('vatID', $schema);
    }

    public function test_the_home_page_carries_the_business_and_names_it_once(): void
    {
        $this->company();

        $this->get('/')
            ->assertOk()
            ->assertSee('"@type":"OnlineStore"', false)
            ->assertSee('"publisher":{"@id":"'.OrganizationSchema::id().'"}', false);
    }

    public function test_a_category_lists_the_products_of_the_page_being_read(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(25)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $page = $this->get('/categories/'.$category->slug.'?page=2')->assertOk();

        $page->assertSee('"@type":"CollectionPage"', false);
        $page->assertSee('"@type":"BreadcrumbList"', false);
        // Twenty-five products, twenty to a page: five on page two, and the
        // first of them is the twenty-first of the category.
        $page->assertSee('"numberOfItems":25', false);
        $page->assertSee('"position":21', false);
    }
}
