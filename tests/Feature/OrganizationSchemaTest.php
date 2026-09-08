<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\MarketplaceSetting;
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
        // Twenty-five products, twenty-four to a page: one on page two, and
        // it is the twenty-fifth of the category.
        $page->assertSee('"numberOfItems":25', false);
        $page->assertSee('"position":25', false);
    }

    /**
     * The one-word spelling is the wordmark, and it is what people type. It
     * is declared as a name of the same business so the two spellings are
     * not two unrelated strings to a search engine.
     */
    public function test_the_business_is_also_named_by_its_one_word_spelling(): void
    {
        $schema = OrganizationSchema::for($this->company());

        $this->assertSame('Armo Outdoor', $schema['name']);
        $this->assertSame('ArmoOutdoor', $schema['alternateName']);
    }

    public function test_the_shops_other_pages_are_declared_as_the_same_business(): void
    {
        // Read from the marketplace settings, where the NaturaBuy address has
        // always lived: a second copy on the company would be one more place
        // to look and one more to keep in step.
        MarketplaceSetting::current()->update([
            'naturabuy_url' => 'https://www.naturabuy.fr/stores/2811/',
            'vinted_url' => 'https://www.vinted.fr/member/1-armooutdoor',
        ]);

        $this->assertSame(
            ['https://www.naturabuy.fr/stores/2811/', 'https://www.vinted.fr/member/1-armooutdoor'],
            OrganizationSchema::for($this->company())['sameAs'],
        );
    }

    public function test_a_shop_with_no_profile_declares_none(): void
    {
        // sameAs is a claim that these pages are the same business. Empty, it
        // says nothing; pointing at a dead address would say something false.
        $schema = OrganizationSchema::for($this->company());

        $this->assertArrayNotHasKey('sameAs', $schema);
    }

    public function test_one_profile_alone_is_enough(): void
    {
        MarketplaceSetting::current()->update([
            'vinted_url' => 'https://www.vinted.fr/member/1-armooutdoor',
        ]);

        $this->assertSame(
            ['https://www.vinted.fr/member/1-armooutdoor'],
            OrganizationSchema::for($this->company())['sameAs'],
        );
    }

    public function test_the_naturabuy_address_is_not_stored_twice(): void
    {
        // The home page's marketplace block and the structured data read the
        // same column. Two copies of one address drift, and the page would
        // then link one shop while the markup declared another.
        MarketplaceSetting::current()->update(['naturabuy_url' => 'https://www.naturabuy.fr/stores/2811/']);

        $this->assertSame(
            [MarketplaceSetting::current()->naturabuy_url],
            OrganizationSchema::for($this->company())['sameAs'],
        );
    }
}
