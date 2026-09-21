<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\User;
use App\Services\Octopia\OctopiaDescriptionWriter;
use App\Support\Octopia\Exporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClassConstant;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * The description a product is sent to Cdiscount with, written by Claude.
 *
 * Claude is never called. What has to hold is that this description is what
 * Octopia is given instead of the shop's, that it is saved and shown like any
 * field of the listing, that the button is only there where a key is
 * configured and says what went wrong, and that what Claude is asked for is a
 * plain description consistent with the category the seller chose.
 */
class OctopiaDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function template(): OctopiaTemplate
    {
        return OctopiaTemplate::query()->create(['code' => '100304', 'name' => 'CASQUETTE', 'is_variant' => false, 'fields' => []]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create($overrides + [
            'name' => ['fr' => 'Casquette camouflage', 'en' => 'Camo cap'],
            'sku' => 'CAP-CAMO',
            'gtin' => '3760452700954',
            'image' => 'products/cap.webp',
            'meta_description' => 'Le résumé du produit.',
            'description' => ['fr' => '<p>Casquette pour la randonnée, la chasse, l\'airsoft et le sport. <strong>Ajustable.</strong></p>'],
        ]);
    }

    private function listing(Product $product, OctopiaTemplate $template, ?string $description = null): CdiscountListing
    {
        return CdiscountListing::query()->create([
            'product_id' => $product->id,
            'octopia_template_id' => $template->id,
            'values' => [],
            'per_variant' => [],
            'description' => $description,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, OctopiaTemplate $template): array
    {
        return (new Exporter($template))->lines(
            $template->listings()->with(['variants', 'product.variants', 'product.images'])->get()
        )[0]['payload'];
    }

    private function writerReturning(string $description): void
    {
        $this->instance(OctopiaDescriptionWriter::class, new class($description) extends OctopiaDescriptionWriter
        {
            public function __construct(private readonly string $description)
            {
                parent::__construct('test-key');
            }

            public function write(Product $product, OctopiaTemplate $template): string
            {
                return $this->description;
            }
        });
    }

    // ------------------------------------------------------- what is sent

    public function test_the_description_written_for_cdiscount_is_sent_instead_of_the_shops(): void
    {
        $template = $this->template();
        $product = $this->product();
        $this->listing($product, $template, 'Casquette en coton, réglable à l\'arrière.');

        $payload = $this->payload($product, $template);

        // Not the meta description, not the long one.
        $this->assertSame('Casquette en coton, réglable à l\'arrière.', $payload['description']);
    }

    public function test_the_shops_rich_description_is_not_sent_with_it(): void
    {
        $template = $this->template();
        $product = $this->product();
        $this->listing($product, $template, 'Casquette en coton.');

        // The rich description is the long one in HTML: it would carry back
        // the uses the description written for Cdiscount leaves out.
        $this->assertArrayNotHasKey('richMarketingDescription', $this->payload($product, $template));
    }

    public function test_without_one_the_meta_description_stands_in_as_before(): void
    {
        $template = $this->template();
        $product = $this->product();
        $this->listing($product, $template);

        $payload = $this->payload($product, $template);

        $this->assertSame('Le résumé du produit.', $payload['description']);
        $this->assertArrayHasKey('richMarketingDescription', $payload);
    }

    public function test_a_description_of_its_own_is_enough_to_have_one(): void
    {
        $template = $this->template();
        $product = $this->product(['meta_description' => null, 'description' => ['fr' => '']]);
        $this->listing($product, $template, 'Casquette en coton.');

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount')
            ->assertOk()
            ->assertDontSee('>Description<', false);
    }

    // ------------------------------------------------------------ the field

    public function test_the_field_is_saved_with_the_listing(): void
    {
        $template = $this->template();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'description' => "  Casquette en coton.\nRéglable.  ",
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("Casquette en coton.\nRéglable.", $product->fresh()->cdiscountListing->description);
    }

    public function test_an_emptied_field_goes_back_to_the_shops_description(): void
    {
        $template = $this->template();
        $product = $this->product();
        $this->listing($product, $template, 'Casquette en coton.');

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', ['octopia_template_id' => $template->id, 'description' => '   '])
            ->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->cdiscountListing->description);
    }

    public function test_a_description_past_what_octopia_takes_is_refused(): void
    {
        $template = $this->template();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'description' => str_repeat('a', OctopiaDescriptionWriter::LIMIT + 1),
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_the_page_shows_the_field_with_what_was_saved(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $template = $this->template();
        $product = $this->product();
        $this->listing($product, $template, 'Casquette en coton.');

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/cdiscount')
            ->assertOk()
            ->assertSee('Description for Cdiscount')
            ->assertSee('name="description"', false)
            ->assertSee('Casquette en coton.')
            ->assertSee('Write with Claude')
            ->assertSee(route('admin.products.cdiscount.describe', $product), false);
    }

    public function test_without_a_key_there_is_no_button_but_the_field_stays(): void
    {
        config(['services.anthropic.key' => null]);
        $this->template();

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$this->product()->id.'/cdiscount')
            ->assertOk()
            ->assertSee('Description for Cdiscount')
            ->assertDontSee('data-octopia-describe', false);
    }

    // --------------------------------------------------------- the endpoint

    public function test_it_answers_with_the_description_and_saves_nothing(): void
    {
        $this->writerReturning('Casquette en coton, réglable.');
        $template = $this->template();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$product->id.'/cdiscount/describe', ['octopia_template_id' => $template->id])
            ->assertOk()
            ->assertExactJson(['description' => 'Casquette en coton, réglable.']);

        $this->assertSame(0, CdiscountListing::query()->count());
    }

    public function test_without_a_key_the_endpoint_says_so(): void
    {
        $this->instance(OctopiaDescriptionWriter::class, new OctopiaDescriptionWriter(null));

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$this->product()->id.'/cdiscount/describe', ['octopia_template_id' => $this->template()->id])
            ->assertStatus(503)
            ->assertJsonPath('message', 'No Anthropic API key is configured on this environment.');
    }

    public function test_a_failure_upstream_is_reported_rather_than_swallowed(): void
    {
        $this->instance(OctopiaDescriptionWriter::class, new class extends OctopiaDescriptionWriter
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function write(Product $product, OctopiaTemplate $template): string
            {
                throw new RuntimeException('Claude could not be reached.');
            }
        });

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$this->product()->id.'/cdiscount/describe', ['octopia_template_id' => $this->template()->id])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Claude could not be reached.');
    }

    public function test_a_category_that_does_not_exist_is_refused(): void
    {
        $this->writerReturning('x');

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$this->product()->id.'/cdiscount/describe', ['octopia_template_id' => 9999])
            ->assertStatus(422);
    }

    // ------------------------------------------------- what Claude is asked

    public function test_the_text_is_cleaned_to_what_octopia_takes(): void
    {
        $writer = new OctopiaDescriptionWriter('test-key');

        $clean = $writer->clean("<p>Casquette   en coton \u{2014} réglable.</p>\n\n  <strong>Ajustable</strong>  &amp; légère.  ");

        // No HTML, no long dash, no runs of spaces or blank lines.
        $this->assertSame("Casquette en coton - réglable.\nAjustable & légère.", $clean);
        $this->assertSame(OctopiaDescriptionWriter::LIMIT, mb_strlen($writer->clean(str_repeat('a', 5000))));
    }

    public function test_the_brief_names_the_category_and_gives_the_sheet(): void
    {
        $product = $this->product([
            'brand' => 'Armo',
            'weight_grams' => 90,
            'characteristics' => [['label' => 'Matière', 'value' => '100 % coton']],
        ]);
        $template = $this->template();

        $brief = (new ReflectionMethod(OctopiaDescriptionWriter::class, 'brief'))
            ->invoke(new OctopiaDescriptionWriter('test-key'), $product, $template);

        $this->assertStringContainsString('Catégorie Cdiscount choisie par le vendeur : CASQUETTE', $brief);
        $this->assertStringContainsString('Nom : Casquette camouflage', $brief);
        $this->assertStringContainsString('Marque : Armo', $brief);
        $this->assertStringContainsString('Poids : 90 g', $brief);
        $this->assertStringContainsString('Matière : 100 % coton', $brief);
        // The source is text, not HTML.
        $this->assertStringContainsString('Casquette pour la randonnée', $brief);
        $this->assertStringNotContainsString('<strong>', $brief);
    }

    public function test_the_instructions_keep_the_description_true_to_the_chosen_category(): void
    {
        $prompt = (new ReflectionClassConstant(OctopiaDescriptionWriter::class, 'SYSTEM_PROMPT'))->getValue();

        // The article is described as its category names it, never as another kind.
        $this->assertStringContainsString('cohérente avec cette catégorie', $prompt);
        $this->assertMatchesRegularExpression("/Ne le présente\s+jamais comme un autre type d'article/", $prompt);
        // A list of uses is what made a product read as something else.
        $this->assertStringContainsString("N'énumère pas les activités ou les usages", $prompt);
        $this->assertStringContainsString('Ne reprends pas les rubriques « Style »', $prompt);
        $this->assertMatchesRegularExpression('/Chaque affirmation de ta description doit se retrouver dans la fiche/', $prompt);
        $this->assertMatchesRegularExpression("/N'ajoute aucune situation d'usage/", $prompt);
        $this->assertStringContainsString("N'invente rien", $prompt);
        // The facts a description invents most readily, named and forbidden.
        $this->assertMatchesRegularExpression("/aucun conseil\s+d'entretien ni de lavage/", $prompt);
        $this->assertMatchesRegularExpression('/aucune promesse de durabilité/', $prompt);
        // Nothing that belongs to the shop rather than the article.
        $this->assertStringContainsString('Aucun lien, aucun nom de boutique', $prompt);
    }
}
