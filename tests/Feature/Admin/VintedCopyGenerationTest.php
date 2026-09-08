<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Services\VintedCopywriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

            public function write(Product $product): array
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

            public function write(Product $product): array
            {
                throw new RuntimeException($this->message);
            }
        });
    }

    public function test_the_button_is_offered_when_a_key_is_configured(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.edit', Product::factory()->create()))
            ->assertOk()
            ->assertSee('Write with Claude');
    }

    public function test_without_a_key_the_button_is_not_there_at_all(): void
    {
        // A button that answers "not configured" every time is worse than no
        // button: it looks broken rather than absent.
        config(['services.anthropic.key' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.edit', Product::factory()->create()))
            ->assertOk()
            ->assertDontSee('Write with Claude');
    }

    public function test_it_answers_with_a_title_and_a_description(): void
    {
        $this->writerReturning(['title' => 'Cagoule camo', 'description' => "Neuve.\nEnvoi rapide."]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.products.vinted.generate', Product::factory()->create()))
            ->assertOk()
            ->assertJson(['title' => 'Cagoule camo', 'description' => "Neuve.\nEnvoi rapide."]);
    }

    public function test_nothing_is_saved_by_generating(): void
    {
        // The wording lands in the form, not in the table: the admin still
        // decides, and Save is still what decides it.
        $product = Product::factory()->create();
        $this->writerReturning(['title' => 'Cagoule camo', 'description' => 'Neuve.']);

        $this->actingAs($this->admin())->postJson(route('admin.products.vinted.generate', $product));

        $this->assertDatabaseCount('vinted_listings', 0);
    }

    public function test_a_failure_upstream_is_reported_rather_than_swallowed(): void
    {
        $this->writerFailing('Claude answered in an unexpected shape.');

        $this->actingAs($this->admin())
            ->postJson(route('admin.products.vinted.generate', Product::factory()->create()))
            ->assertStatus(502)
            ->assertJson(['message' => 'Claude answered in an unexpected shape.']);
    }

    public function test_an_environment_without_a_key_says_so(): void
    {
        config(['services.anthropic.key' => null]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.products.vinted.generate', Product::factory()->create()))
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

    public function test_a_long_title_is_cut_where_vinted_cuts_it(): void
    {
        $copy = $this->parse(json_encode([
            'title' => str_repeat('a', 90),
            'description' => 'Neuve.',
        ]));

        $this->assertSame(60, mb_strlen($copy['title']));
    }

    public function test_an_answer_missing_a_field_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parse('{"title": "Cagoule camo"}');
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
