<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Octopia\OctopiaAttributeWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Claude filling a category's attributes from the product sheet.
 *
 * Claude is never called. What has to hold is that the button is only there
 * where a key is configured, that what already answered is left alone, that a
 * failure is said rather than swallowed, and above all that nothing reaches
 * the form which the category would refuse: the answer comes from outside and
 * can be any shape.
 */
class OctopiaAttributeFillTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return list<array<string, mixed>> */
    private function fields(): array
    {
        return [
            ['column' => null, 'code' => '3264', 'label' => 'Couleur principale', 'required' => false, 'kind' => 'monoranged', 'constraint' => '', 'options' => ['Noir', 'Beige', 'Vert olive']],
            ['column' => null, 'code' => '3263', 'label' => 'Couleur(s)', 'required' => true, 'kind' => 'multiranged', 'constraint' => '', 'options' => ['Noir', 'Beige']],
            ['column' => null, 'code' => '24061', 'label' => 'Matières', 'required' => false, 'kind' => 'mono', 'constraint' => '', 'options' => []],
            ['column' => null, 'code' => '999996', 'label' => 'Poids emballé', 'required' => false, 'kind' => 'mono', 'constraint' => 'Numérique · Unité : kg', 'options' => []],
            ['column' => null, 'code' => '46831', 'label' => 'Taille chapeau - bonnet', 'required' => true, 'kind' => 'monoranged', 'constraint' => '', 'options' => ['Taille unique', 'M']],
        ];
    }

    private function template(): OctopiaTemplate
    {
        return OctopiaTemplate::query()->create(['code' => '0U0O05', 'name' => 'CAGOULE TECHNIQUE', 'fields' => $this->fields()]);
    }

    /** A writer that answers as told and remembers what it was asked. */
    private function writerReturning(array $answer, ?object $spy = null): void
    {
        $this->instance(OctopiaAttributeWriter::class, new class($answer, $spy) extends OctopiaAttributeWriter
        {
            public function __construct(private readonly array $answer, private readonly ?object $spy)
            {
                parent::__construct('test-key');
            }

            public function fill(Product $product, OctopiaTemplate $template, array $perVariant = [], array $skip = []): array
            {
                if ($this->spy !== null) {
                    $this->spy->called = ['template' => $template->id, 'perVariant' => $perVariant, 'skip' => $skip, 'product' => $product->id];
                }

                return $this->answer;
            }
        });
    }

    /** @return array<string, mixed> */
    private function normalize(array $input, array $perVariant, Product $product): array
    {
        return (new ReflectionMethod(OctopiaAttributeWriter::class, 'normalize'))
            ->invoke(new OctopiaAttributeWriter('test-key'), $input, $this->fields(), $perVariant, $product->load('variants'));
    }

    // ------------------------------------------------------------- the button

    public function test_the_button_is_offered_when_a_key_is_configured(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $template = $this->template();
        $product = Product::factory()->create();
        CdiscountListing::query()->create(['product_id' => $product->id, 'octopia_template_id' => $template->id, 'values' => [], 'per_variant' => []]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/cdiscount')
            ->assertOk()
            ->assertSee('Fill with Claude')
            ->assertSee('Nothing is saved until you press Save')
            ->assertSee(route('admin.products.cdiscount.generate', $product), false);
    }

    public function test_without_a_key_the_button_is_not_there_at_all(): void
    {
        config(['services.anthropic.key' => null]);
        $this->template();

        $this->actingAs($this->admin())
            ->get('/admin/products/'.Product::factory()->create()->id.'/cdiscount')
            ->assertOk()
            ->assertDontSee('Fill with Claude');
    }

    // ---------------------------------------------------------- the endpoint

    public function test_it_answers_with_what_the_writer_found(): void
    {
        $spy = new \stdClass;
        $this->writerReturning(['values' => ['3264' => 'Noir'], 'variants' => []], $spy);
        $template = $this->template();
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$product->id.'/cdiscount/generate', [
                'octopia_template_id' => $template->id,
                'per_variant' => ['46831'],
                'skip' => ['24061'],
            ])
            ->assertOk()
            ->assertExactJson(['values' => ['3264' => 'Noir'], 'variants' => []]);

        // What was already answered, and what is answered per variant, is passed on.
        $this->assertSame(['template' => $template->id, 'perVariant' => ['46831'], 'skip' => ['24061'], 'product' => $product->id], $spy->called);
    }

    public function test_nothing_is_saved_by_filling(): void
    {
        $this->writerReturning(['values' => ['3264' => 'Noir'], 'variants' => []]);
        $template = $this->template();
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.$product->id.'/cdiscount/generate', ['octopia_template_id' => $template->id])
            ->assertOk();

        // The answer lands in the form; Save is what decides.
        $this->assertSame(0, CdiscountListing::query()->count());
    }

    public function test_without_a_key_the_endpoint_says_so(): void
    {
        $this->instance(OctopiaAttributeWriter::class, new OctopiaAttributeWriter(null));

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.Product::factory()->create()->id.'/cdiscount/generate', ['octopia_template_id' => $this->template()->id])
            ->assertStatus(503)
            ->assertJsonPath('message', 'No Anthropic API key is configured on this environment.');
    }

    public function test_a_failure_upstream_is_reported_rather_than_swallowed(): void
    {
        $this->instance(OctopiaAttributeWriter::class, new class extends OctopiaAttributeWriter
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function fill(Product $product, OctopiaTemplate $template, array $perVariant = [], array $skip = []): array
            {
                throw new RuntimeException('Claude could not be reached.');
            }
        });

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.Product::factory()->create()->id.'/cdiscount/generate', ['octopia_template_id' => $this->template()->id])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Claude could not be reached.');
    }

    public function test_a_category_that_does_not_exist_is_refused(): void
    {
        $this->writerReturning(['values' => [], 'variants' => []]);

        $this->actingAs($this->admin())
            ->postJson('/admin/products/'.Product::factory()->create()->id.'/cdiscount/generate', ['octopia_template_id' => 9999])
            ->assertStatus(422);
    }

    public function test_a_customer_cannot_use_it(): void
    {
        $this->writerReturning(['values' => [], 'variants' => []]);

        $this->actingAs(User::factory()->create())
            ->postJson('/admin/products/'.Product::factory()->create()->id.'/cdiscount/generate', ['octopia_template_id' => $this->template()->id])
            ->assertStatus(302);
    }

    // ------------------------------------------------- what is held to account

    public function test_what_the_form_is_already_answered_is_not_asked_again(): void
    {
        $pending = (new OctopiaAttributeWriter('test-key'))->pending($this->template(), ['3264', '24061']);

        $this->assertSame(['3263', '999996', '46831'], array_column($pending, 'code'));
    }

    public function test_a_closed_list_takes_only_its_own_options_in_their_own_spelling(): void
    {
        $product = Product::factory()->create();

        $answer = $this->normalize(['values' => [
            // Case and accents set aside to find the option; its spelling is kept.
            ['code' => '3264', 'value' => 'vert  olive'],
            ['code' => '46831', 'value' => 'Taille unique'],
        ]], [], $product);

        // The double space is not the option: nothing near enough is taken.
        $this->assertSame(['46831' => 'Taille unique'], $answer['values']);

        $answer = $this->normalize(['values' => [['code' => '3264', 'value' => 'VERT OLIVE']]], [], $product);

        $this->assertSame(['3264' => 'Vert olive'], $answer['values']);
    }

    public function test_a_colour_that_is_not_on_octopias_list_is_dropped(): void
    {
        $answer = $this->normalize(['values' => [['code' => '3264', 'value' => 'Bleu marine']]], [], Product::factory()->create());

        $this->assertSame([], $answer['values']);
    }

    public function test_several_values_are_kept_one_by_one_against_the_list(): void
    {
        $answer = $this->normalize(['values' => [['code' => '3263', 'value' => 'noir ; Beige ; Rose']]], [], Product::factory()->create());

        // Rose is not on the list; the two that are, are kept, joined by semicolons.
        $this->assertSame(['3263' => 'Noir;Beige'], $answer['values']);
    }

    public function test_a_number_is_kept_as_a_number(): void
    {
        $product = Product::factory()->create();

        $this->assertSame(['999996' => '0.05'], $this->normalize(['values' => [['code' => '999996', 'value' => '0,05']]], [], $product)['values']);
        $this->assertSame([], $this->normalize(['values' => [['code' => '999996', 'value' => '50 g']]], [], $product)['values']);
    }

    public function test_free_text_is_kept_as_written(): void
    {
        $answer = $this->normalize(['values' => [['code' => '24061', 'value' => '  100 % polyester  ']]], [], Product::factory()->create());

        $this->assertSame(['24061' => '100 % polyester'], $answer['values']);
    }

    public function test_a_code_the_category_does_not_have_is_dropped(): void
    {
        $answer = $this->normalize(['values' => [['code' => '123456', 'value' => 'x'], ['code' => '24061', 'value' => '']]], [], Product::factory()->create());

        $this->assertSame([], $answer['values']);
    }

    public function test_variant_answers_are_kept_only_for_this_products_variants_and_the_codes_asked_per_variant(): void
    {
        $product = Product::factory()->create();
        $mine = ProductVariant::create(['product_id' => $product->id, 'sku' => 'A', 'quantity' => 1]);
        $other = ProductVariant::create(['product_id' => Product::factory()->create()->id, 'sku' => 'B', 'quantity' => 1]);

        $answer = $this->normalize(['variants' => [
            ['variant_id' => $mine->id, 'code' => '46831', 'value' => 'M'],
            // Another product's variant, and a code not asked per variant.
            ['variant_id' => $other->id, 'code' => '46831', 'value' => 'M'],
            ['variant_id' => $mine->id, 'code' => '24061', 'value' => 'Coton'],
            // Not a size the list has.
            ['variant_id' => $mine->id, 'code' => '3264', 'value' => 'Jaune'],
        ]], ['46831'], $product);

        $this->assertSame([$mine->id => ['46831' => 'M']], $answer['variants']);
    }

    // -------------------------------------------------- what Claude is given

    public function test_the_brief_is_the_product_sheet_and_the_attributes_to_answer(): void
    {
        $product = Product::factory()->create([
            'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava'],
            'brand' => 'Armo',
            'weight_grams' => 50,
            'description' => ['fr' => '<p>Une cagoule <strong>respirante</strong> en polyester.</p>'],
            'characteristics' => [['label' => 'Matière', 'value' => '100 % polyester']],
        ]);
        ProductVariant::create(['product_id' => $product->id, 'attribute_values' => [['label' => 'Taille', 'value' => 'M']], 'sku' => 'CAG-M', 'quantity' => 1]);
        $template = $this->template();
        $writer = new OctopiaAttributeWriter('test-key');

        $brief = fn (array $perVariant) => (new ReflectionMethod(OctopiaAttributeWriter::class, 'brief'))
            ->invoke($writer, $product->load(['variants', 'category']), $template, $writer->pending($template, ['24061']), $perVariant);

        $text = $brief(['46831']);

        $this->assertStringContainsString('Nom : Cagoule désert', $text);
        $this->assertStringContainsString('Marque : Armo', $text);
        $this->assertStringContainsString('Poids : 50 g', $text);
        $this->assertStringContainsString('Matière : 100 % polyester', $text);
        $this->assertStringContainsString('Une cagoule respirante en polyester.', $text);
        // Text, not HTML.
        $this->assertStringNotContainsString('<strong>', $text);

        // The attributes, with what tells Claude how to answer.
        $this->assertStringContainsString('code 46831 : Taille chapeau - bonnet (obligatoire) (par variante)', $text);
        $this->assertStringContainsString('Liste fermée, une option exactement : Taille unique | M', $text);
        $this->assertStringContainsString('Contrainte : Numérique · Unité : kg', $text);
        // What was answered already is not in the list to fill.
        $this->assertStringNotContainsString('code 24061', $text);

        // The variants are shown only when an attribute is asked per variant.
        $this->assertStringContainsString('variante ', $text);
        $this->assertStringNotContainsString('Variantes :', $brief([]));
    }

    public function test_the_instructions_forbid_guessing(): void
    {
        $prompt = (new \ReflectionClassConstant(OctopiaAttributeWriter::class, 'SYSTEM_PROMPT'))->getValue();

        $this->assertStringContainsString("tu n'inventes", $prompt);
        $this->assertStringContainsString('Un attribut laissé vide est une bonne', $prompt);
        $this->assertStringContainsString('exactement l\'une des', $prompt);
    }
}
