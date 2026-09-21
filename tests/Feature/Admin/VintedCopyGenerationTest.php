<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VintedListing;
use App\Services\VintedCopywriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * The "Write with Claude" button on the Vinted listing page.
 *
 * What has to hold: it proposes and never saves, it is absent where no key is
 * configured, and a failure upstream is said in the page rather than swallowed
 * into an empty field. The parsing is tested on its own — the answer is the
 * one thing here that comes from outside and can arrive in any shape.
 */
class VintedCopyGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function writerReturning(array $copy): void
    {
        $this->instance(VintedCopywriter::class, new class($copy) extends VintedCopywriter
        {
            public function __construct(private readonly array $copy)
            {
                parent::__construct('test-key');
            }

            public function write(Product $product, ?\App\Models\VintedListing $listing = null): array
            {
                return $this->copy;
            }
        });
    }

    private function writerFailing(string $message): void
    {
        $this->instance(VintedCopywriter::class, new class($message) extends VintedCopywriter
        {
            public function __construct(private readonly string $message)
            {
                parent::__construct('test-key');
            }

            public function write(Product $product, ?\App\Models\VintedListing $listing = null): array
            {
                throw new RuntimeException($this->message);
            }
        });
    }

    public function test_the_button_is_offered_when_a_key_is_configured(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $this->actingAs($this->admin())
            ->get($this->vintedRoute('edit', Product::factory()->create()))
            ->assertOk()
            ->assertSee('Write with Claude');
    }

    public function test_without_a_key_the_button_is_not_there_at_all(): void
    {
        // A button that answers "not configured" every time is worse than no
        // button: it looks broken rather than absent.
        config(['services.anthropic.key' => null]);

        $this->actingAs($this->admin())
            ->get($this->vintedRoute('edit', Product::factory()->create()))
            ->assertOk()
            ->assertDontSee('Write with Claude');
    }

    public function test_it_answers_with_a_title_and_a_description(): void
    {
        $this->writerReturning(['title' => 'Cagoule camo', 'description' => "Neuve.\nEnvoi rapide.", 'price' => 12.5]);

        $this->actingAs($this->admin())
            ->postJson($this->vintedRoute('generate', Product::factory()->create()))
            ->assertOk()
            ->assertJson(['title' => 'Cagoule camo', 'description' => "Neuve.\nEnvoi rapide.", 'price' => 12.5]);
    }

    public function test_nothing_is_saved_by_generating(): void
    {
        // The wording lands in the form, not in the table: the admin still
        // decides, and Save is still what decides it.
        $product = Product::factory()->create();
        $this->writerReturning(['title' => 'Cagoule camo', 'description' => 'Neuve.', 'price' => 12.5]);

        $this->actingAs($this->admin())->postJson($this->vintedRoute('generate', $product));

        // Only the blank draft the address named: generating adds nothing
        // and fills nothing.
        $this->assertDatabaseCount('vinted_listings', 1);
        $this->assertTrue(VintedListing::query()->first()->isEmpty());
    }

    public function test_a_failure_upstream_is_reported_rather_than_swallowed(): void
    {
        $this->writerFailing('Claude answered in an unexpected shape.');

        $this->actingAs($this->admin())
            ->postJson($this->vintedRoute('generate', Product::factory()->create()))
            ->assertStatus(502)
            ->assertJson(['message' => 'Claude answered in an unexpected shape.']);
    }

    public function test_an_environment_without_a_key_says_so(): void
    {
        config(['services.anthropic.key' => null]);

        $this->actingAs($this->admin())
            ->postJson($this->vintedRoute('generate', Product::factory()->create()))
            ->assertStatus(503);
    }

    public function test_a_fenced_answer_is_still_read(): void
    {
        // Asked for bare JSON, a model wraps it in a code fence often enough
        // that the fence must not cost the admin a click.
        $copy = $this->parse("Voici :\n```json\n{\"title\": \"Cagoule camo\", \"description\": \"Neuve.\"}\n```");

        $this->assertSame('Cagoule camo', $copy['title']);
        $this->assertSame('Neuve.', $copy['description']);
    }

    public function test_a_long_title_is_cut_at_vinted_s_limit(): void
    {
        $copy = $this->parse(json_encode([
            'title' => str_repeat('a', 140),
            'description' => 'Neuve.',
        ]));

        $this->assertSame(100, mb_strlen($copy['title']));

        // Under the limit, nothing is touched.
        $ninety = $this->parse(json_encode(['title' => str_repeat('a', 90), 'description' => 'Neuve.']));
        $this->assertSame(90, mb_strlen($ninety['title']));
    }

    public function test_an_answer_missing_a_field_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parse('{"title": "Cagoule camo"}');
    }

    public function test_a_price_comes_back_with_the_wording(): void
    {
        $copy = $this->parse('{"title": "Cagoule camo", "description": "Neuve.", "price": 12.5}');

        $this->assertSame(12.5, $copy['price']);
    }

    /**
     * The three fields are asked for as a tool call, which is what makes them
     * arrive typed and all present. Free-text JSON came back fenced, or with
     * a decimal comma, or without the price — each costing a silent field.
     */
    public function test_the_answer_is_read_from_the_tool_call(): void
    {
        $block = new class
        {
            public string $type = 'tool_use';

            public string $name = 'annonce_vinted';

            public array $input = ['title' => 'Cagoule camo', 'description' => 'Neuve.', 'price' => 8.5];
        };

        $method = new ReflectionMethod(VintedCopywriter::class, 'answer');
        $copy = $method->invoke(new VintedCopywriter('test-key'), [$block]);

        $this->assertSame('Cagoule camo', $copy['title']);
        $this->assertSame(8.5, $copy['price']);
    }

    /**
     * An answer cut short by the token ceiling comes back as text rather than
     * as a call. Reading it beats telling the admin that nothing came.
     */
    public function test_a_text_answer_is_still_read_when_no_tool_was_called(): void
    {
        $block = new class
        {
            public string $type = 'text';

            public string $text = '{"title": "Cagoule camo", "description": "Neuve.", "price": 8.5}';
        };

        $method = new ReflectionMethod(VintedCopywriter::class, 'answer');
        $copy = $method->invoke(new VintedCopywriter('test-key'), [$block]);

        $this->assertSame('Cagoule camo', $copy['title']);
    }

    /**
     * The prompt is French and asks for a number; a model writes 24,50 all
     * the same. The comma cost the price field a whole generation once, in
     * silence, which is what this keeps from coming back.
     */
    public function test_a_french_decimal_is_still_a_price(): void
    {
        // The euro sign arrives after a plain space, a no-break space or a
        // narrow one depending on who formatted it.
        foreach (['"24,50"', '"24,50 €"', "\"24,50\u{00a0}€\"", "\"24,50\u{202f}€\"", '"24.50"', '24.5'] as $written) {
            $copy = $this->parse('{"title": "T", "description": "D", "price": '.$written.'}');

            $this->assertSame(24.5, $copy['price'], "Unread: {$written}");
        }
    }

    /**
     * A price is a suggestion, and the two fields beside it are not. An
     * answer that forgets it — or writes "environ 12 €" — must still fill the
     * title and the text rather than costing the whole click.
     */
    public function test_an_unreadable_price_leaves_the_field_alone(): void
    {
        foreach (['{"title": "T", "description": "D"}', '{"title": "T", "description": "D", "price": "environ 12"}'] as $answer) {
            $copy = $this->parse($answer);

            $this->assertNull($copy['price']);
            $this->assertSame('T', $copy['title']);
        }
    }

    /**
     * The listing has to leave room for the offer that always comes, and it
     * has to leave the shop something. Both bounds are in the prompt: the
     * margin, and the purchase cost as a floor.
     */
    public function test_the_prompt_prices_for_haggling_and_never_under_cost(): void
    {
        $prompt = (new ReflectionClass(VintedCopywriter::class))->getConstant('SYSTEM_PROMPT');

        $this->assertStringContainsString('négocie', $prompt);
        $this->assertStringContainsString('10 à 20', $prompt);
        $this->assertStringContainsString("sous le coût d'achat", $prompt);
    }

    /**
     * Vinted's moderation removes a listing on the vocabulary alone, before
     * anybody reads it — and the shop sells accessories for shooting sports.
     * The instruction that keeps that vocabulary out is the whole reason a
     * generated listing survives, so it is not left to be tidied away.
     */
    public function test_the_prompt_keeps_the_listing_clear_of_flagged_vocabulary(): void
    {
        $prompt = (new ReflectionClass(VintedCopywriter::class))->getConstant('SYSTEM_PROMPT');

        $this->assertStringContainsString('modère', $prompt);

        foreach (['arme', 'munition', 'militaire', 'violence'] as $word) {
            $this->assertStringContainsString($word, $prompt, "The prompt must name « {$word} » as a word to avoid");
        }
    }

    /**
     * A balaclava listing was flagged for « airsoft » and for a face covering
     * worn « sous un casque, un masque »: the word list had no airsoft in it,
     * and the prompt itself named airsoft as what the shop sells. Vinted's
     * catalogue rules ban replicas and official uniforms by name, and an item
     * that is banned outright is said to be, rather than renamed until it
     * passes.
     */
    public function test_the_prompt_follows_vinted_catalogue_rules(): void
    {
        $prompt = (new ReflectionClass(VintedCopywriter::class))->getConstant('SYSTEM_PROMPT');

        foreach (['« airsoft »', '« réplique »', '« tir »', '« chasse »', 'uniformes', 'Article interdit sur Vinted', 'ne le maquille pas', 'froid, du vent'] as $rule) {
            $this->assertStringContainsString($rule, $prompt, "The prompt lost « {$rule} »");
        }

        // The prompt no longer introduces the shop by the very words it bans.
        $this->assertStringNotContainsString("d'équipement de tir sportif, chasse, airsoft", $prompt);
        $this->assertStringNotContainsString("\u{2014}", $prompt);
    }

    /**
     * A title used to be rewritten from scratch, and no longer looked like the
     * product it sold. It now starts from the catalogue name, cleaned up, then
     * adds what makes it findable.
     */
    public function test_the_prompt_asks_for_a_title_that_says_what_the_item_is(): void
    {
        $prompt = (new ReflectionClass(VintedCopywriter::class))->getConstant('SYSTEM_PROMPT');

        // Vinted cuts the display around 60 characters on a phone.
        $this->assertStringContainsString('60 caractères au plus', $prompt);
        // The title starts from the sheet's own name and keeps its words.
        $this->assertStringContainsString('part du nom de la fiche produit', $prompt);
        $this->assertStringContainsString('garde', $prompt);
        $this->assertStringContainsString('majuscule', $prompt);
        $this->assertStringContainsString('mots-clés', $prompt);
        // The camouflage naming rule the catalogue follows holds here too.
        $this->assertStringContainsString('multi-terrain', $prompt);
        $this->assertStringContainsString('« CP »', $prompt);
    }

    /**
     * The shop sells one article in several colourways, a product each, and
     * three listings that differ by one word read as duplicates on Vinted.
     * The brief carries what the neighbours already say, so the model can
     * write something else.
     */
    public function test_the_brief_shows_the_listings_of_neighbouring_products(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();

        $product = Product::factory()->create(['category_id' => $category->id, 'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava']]);

        $neighbour = Product::factory()->create(['category_id' => $category->id]);
        VintedListing::query()->create([
            'product_id' => $neighbour->id,
            'title' => 'Cagoule cache-cou en polyester, camouflage pixel gris',
            'description' => "Cagoule intégrale.\nEnvoi rapide.",
        ]);

        // The draft being written is not a neighbour, nor is another category's.
        $own = VintedListing::query()->create(['product_id' => $product->id, 'title' => 'Ce que disait la version précédente']);
        $elsewhere = Product::factory()->create(['category_id' => $other->id]);
        VintedListing::query()->create(['product_id' => $elsewhere->id, 'title' => 'Sac à dos 30 L en nylon']);

        $brief = (new ReflectionMethod(VintedCopywriter::class, 'brief'))
            ->invoke(new VintedCopywriter('test-key'), $product, $own);

        $this->assertStringContainsString('Cagoule cache-cou en polyester, camouflage pixel gris', $brief);
        $this->assertStringContainsString('ne leur ressemblent pas', $brief);
        $this->assertStringContainsString('Cagoule intégrale.', $brief);
        $this->assertStringNotContainsString('Ce que disait la version précédente', $brief);
        $this->assertStringNotContainsString('Sac à dos 30 L en nylon', $brief);
    }

    public function test_the_brief_shows_the_other_drafts_of_the_same_product(): void
    {
        $product = Product::factory()->create();
        $first = VintedListing::query()->create([
            'product_id' => $product->id,
            'title' => 'Cagoule camouflage désert respirante',
            'description' => 'Première annonce.',
        ]);
        $second = VintedListing::query()->create(['product_id' => $product->id]);

        $brief = fn (VintedListing $listing): string => (new ReflectionMethod(VintedCopywriter::class, 'brief'))
            ->invoke(new VintedCopywriter('test-key'), $product, $listing);

        // The blank draft is written against the first one; the first is not
        // told about itself.
        $this->assertStringContainsString('Cagoule camouflage désert respirante', $brief($second));
        $this->assertStringContainsString('Première annonce.', $brief($second));
        $this->assertStringNotContainsString('Cagoule camouflage désert respirante', $brief($first));
    }

    public function test_a_product_whose_neighbours_have_no_listing_is_briefed_as_before(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);

        $brief = (new ReflectionMethod(VintedCopywriter::class, 'brief'))
            ->invoke(new VintedCopywriter('test-key'), $product);

        $this->assertStringNotContainsString('ne leur ressemblent pas', $brief);
    }

    /** Vinted reads look-alike listings as duplicates. */
    public function test_the_prompt_refuses_a_listing_that_looks_like_its_neighbours(): void
    {
        $prompt = (new ReflectionClass(VintedCopywriter::class))->getConstant('SYSTEM_PROMPT');

        $this->assertStringContainsString('doublons', $prompt);
        $this->assertStringContainsString('une ouverture différente', $prompt);
        // Wording varies, facts do not.
        $this->assertStringContainsString('jamais les faits', $prompt);
    }

    /**
     * @return array{title: string, description: string}
     */
    private function parse(string $text): array
    {
        $method = new ReflectionMethod(VintedCopywriter::class, 'parse');

        return $method->invoke(new VintedCopywriter('test-key'), $text);
    }
}
