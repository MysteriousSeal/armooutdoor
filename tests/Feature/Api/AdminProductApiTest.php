<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Database\Seeders\ShippingSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * L'API produits de l'administration, après élargissement.
 *
 * Elle ne savait que lire et modifier neuf champs. Ces tests gardent ce qui
 * lui manquait : créer, atteindre le reste des colonnes, tenir le stock des
 * déclinaisons, taire le prix d'achat, et ne pas se laisser marteler.
 */
class AdminProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
        $this->seed(ShippingSeeder::class);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    private function category(): Category
    {
        return Category::factory()->create();
    }

    // ---------------------------------------------------------------- create

    public function test_a_product_can_be_created(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'name' => 'Gants tactiques Alpha',
            'description' => '<p>Une paire de gants.</p>',
            'category_id' => $this->category()->id,
            'price' => 39.99,
            'quantity' => 4,
            'weight_grams' => 152,
        ], $this->headers())->assertCreated();

        $this->assertDatabaseHas('products', [
            'slug' => 'gants-tactiques-alpha',
            'price_cents' => 3999,
            'weight_grams' => 152,
            'quantity' => 4,
        ]);
        $response->assertJsonPath('data.name', 'Gants tactiques Alpha');
    }

    public function test_creating_a_product_requires_the_essentials(): void
    {
        $this->postJson('/api/admin/products', [], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'category_id', 'price']);
    }

    public function test_a_created_slug_never_collides(): void
    {
        $payload = [
            'name' => 'Gants Alpha',
            'description' => 'x',
            'category_id' => $this->category()->id,
            'price' => 10,
        ];

        $this->postJson('/api/admin/products', $payload, $this->headers())->assertCreated();
        $this->postJson('/api/admin/products', $payload, $this->headers())->assertCreated();

        $this->assertDatabaseHas('products', ['slug' => 'gants-alpha']);
        $this->assertDatabaseHas('products', ['slug' => 'gants-alpha-2']);
    }

    /**
     * `sort_order` classe les vitrines par ordre croissant : un produit créé
     * à zéro passerait devant tout le catalogue déjà rangé.
     */
    public function test_a_created_product_goes_to_the_end_of_the_order(): void
    {
        Product::factory()->create(['sort_order' => 42]);

        $this->postJson('/api/admin/products', [
            'name' => 'Dernier arrive',
            'description' => 'x',
            'category_id' => $this->category()->id,
            'price' => 10,
        ], $this->headers())->assertCreated();

        $this->assertSame(43, Product::query()->where('slug', 'dernier-arrive')->value('sort_order'));
    }

    // ---------------------------------------------------------------- fields

    public function test_the_fields_the_api_could_not_reach_are_now_writable(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion']);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'weight_grams' => 631,
            'carrier_ids' => [1, 2],
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
            'supplier_reference' => '25007',
            'supplier_product_url' => 'https://example.test/p/25007',
            'supplier_price' => 29.27,
            'markup_percent' => 30,
            'image_may_vary' => true,
            'featured' => true,
            'sort_order' => 7,
        ], $this->headers())->assertOk();

        $product->refresh();

        $this->assertSame(631, $product->weight_grams);
        $this->assertSame([1, 2], $product->carrier_ids);
        $this->assertSame($supplier->id, $product->supplier_id);
        $this->assertSame('25007', $product->supplier_reference);
        $this->assertSame(2927, $product->supplier_price_cents);
        $this->assertSame(3000, $product->markup_basis_points);
        $this->assertTrue($product->image_may_vary);
        $this->assertTrue($product->featured);
        $this->assertSame(7, $product->sort_order);
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $product = Product::factory()->create(['weight_grams' => 500, 'sku' => 'KEEP-ME']);

        $this->patchJson('/api/admin/products/'.$product->id, ['price' => 12.5], $this->headers())
            ->assertOk();

        $product->refresh();
        $this->assertSame(1250, $product->price_cents);
        $this->assertSame(500, $product->weight_grams);
        $this->assertSame('KEEP-ME', $product->sku);
    }

    /**
     * `products.image` est NOT NULL : envoyer `null` traversait la validation
     * et remontait en 500. L'effacement se range en chaîne vide.
     */
    public function test_clearing_the_image_does_not_crash(): void
    {
        $product = Product::factory()->create(['image' => 'products/x.webp']);

        foreach ([null, ''] as $cleared) {
            $product->update(['image' => 'products/x.webp']);

            $this->patchJson('/api/admin/products/'.$product->id, ['image' => $cleared], $this->headers())
                ->assertOk();

            $this->assertSame('', $product->fresh()->image);
        }
    }

    // -------------------------------------------------------------- variants

    public function test_variants_can_be_created_updated_and_deleted(): void
    {
        $product = Product::factory()->create();

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [
                ['attributes' => [['label' => 'Taille', 'value' => 'S']], 'sku' => 'V-S', 'quantity' => 2],
                ['attributes' => [['label' => 'Taille', 'value' => 'M']], 'sku' => 'V-M', 'quantity' => 3],
            ],
        ], $this->headers())->assertOk();

        $this->assertSame(2, $product->variants()->count());

        $small = $product->variants()->where('sku', 'V-S')->firstOrFail();
        $medium = $product->variants()->where('sku', 'V-M')->firstOrFail();

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [
                ['id' => $small->id, 'quantity' => 9],
                ['id' => $medium->id, '_delete' => true],
            ],
        ], $this->headers())->assertOk();

        $this->assertSame(9, $small->fresh()->quantity);
        $this->assertNull(ProductVariant::query()->find($medium->id));
    }

    /**
     * Le défaut d'origine : l'API écrivait `quantity` telle quelle sur un
     * produit à déclinaisons, alors que ce total est calculé.
     */
    public function test_the_stock_of_a_variant_product_follows_its_variants(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [
                ['attributes' => [['label' => 'Taille', 'value' => 'S']], 'quantity' => 4],
                ['attributes' => [['label' => 'Taille', 'value' => 'M']], 'quantity' => 6],
            ],
        ], $this->headers())->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_writing_a_quantity_on_a_variant_product_is_refused(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'S']],
            'quantity' => 4,
        ]);
        $product->reconcileQuantity();

        $this->patchJson('/api/admin/products/'.$product->id, ['quantity' => 999], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_a_variant_product_gives_up_its_own_identifiers(): void
    {
        $product = Product::factory()->create(['sku' => 'PARENT-SKU', 'gtin' => '4000844587749']);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [['attributes' => [['label' => 'Taille', 'value' => 'S']], 'sku' => 'CHILD-S']],
        ], $this->headers())->assertOk();

        $product->refresh();
        $this->assertNull($product->sku);
        $this->assertNull($product->gtin);
    }

    public function test_removing_the_last_variant_resets_the_stock(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'S']],
            'quantity' => 7,
        ]);
        $product->reconcileQuantity();
        $this->assertSame(7, $product->fresh()->quantity);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [['id' => $variant->id, '_delete' => true]],
        ], $this->headers())->assertOk();

        $this->assertSame(0, $product->fresh()->quantity);
    }

    // ---------------------------------------------------------- identifiers

    public function test_a_sku_cannot_be_taken_from_a_variant(): void
    {
        $other = Product::factory()->create();
        $other->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'S']],
            'sku' => 'SHARED-SKU',
            'quantity' => 0,
        ]);

        $product = Product::factory()->create();

        $this->patchJson('/api/admin/products/'.$product->id, ['sku' => 'SHARED-SKU'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_a_variant_gtin_cannot_be_taken_from_a_product(): void
    {
        Product::factory()->create(['gtin' => '4000844587749']);
        $product = Product::factory()->create();

        $this->patchJson('/api/admin/products/'.$product->id, [
            'variants' => [['attributes' => [['label' => 'Taille', 'value' => 'S']], 'gtin' => '4000844587749']],
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants.0.gtin');
    }

    // -------------------------------------------------------------- exposure

    public function test_the_purchase_price_never_leaves_the_api(): void
    {
        $product = Product::factory()->create([
            'supplier_price_cents' => 2927,
            'markup_basis_points' => 3000,
        ]);

        foreach ([
            $this->getJson('/api/admin/products', $this->headers()),
            $this->getJson('/api/admin/products/'.$product->id, $this->headers()),
        ] as $response) {
            $response->assertOk();
            $body = $response->getContent();

            $this->assertStringNotContainsString('supplier_price_cents', $body);
            $this->assertStringNotContainsString('markup_basis_points', $body);
            $this->assertStringNotContainsString('2927', $body);
        }
    }

    // -------------------------------------------------------------- throttle

    /**
     * Le groupe `api` n'étant pas défini, aucune limite ne s'appliquait : un
     * jeton se devinait à la vitesse du réseau. On resserre la limite le
     * temps du test plutôt que d'envoyer cent vingt requêtes.
     */
    public function test_the_api_is_rate_limited(): void
    {
        RateLimiter::for('admin-api', fn (): Limit => Limit::perMinute(2)->by('throttle-test'));

        $this->getJson('/api/admin/products', $this->headers())->assertOk();
        $this->getJson('/api/admin/products', $this->headers())->assertOk();
        $this->getJson('/api/admin/products', $this->headers())->assertStatus(429);
    }

    /** Une tentative au mauvais jeton compte aussi, sinon rien ne freine. */
    public function test_a_rejected_token_is_counted_too(): void
    {
        RateLimiter::for('admin-api', fn (): Limit => Limit::perMinute(2)->by('throttle-test-401'));

        $this->getJson('/api/admin/products', ['Authorization' => 'Bearer wrong'])->assertStatus(401);
        $this->getJson('/api/admin/products', ['Authorization' => 'Bearer wrong'])->assertStatus(401);
        $this->getJson('/api/admin/products', ['Authorization' => 'Bearer wrong'])->assertStatus(429);
    }

    // --------------------------------------------------------------- listing

    public function test_the_list_can_be_filtered(): void
    {
        $wanted = Product::factory()->create(['sku' => 'FIND-ME']);
        Product::factory()->create(['sku' => 'OTHER']);

        $this->getJson('/api/admin/products?sku=FIND-ME', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_the_list_can_be_filtered_by_activity_and_page_size(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->getJson('/api/admin/products?is_active=0', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/products?per_page=2', $this->headers())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_the_page_size_is_capped(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/admin/products?per_page=5000', $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_the_list_can_return_only_what_changed(): void
    {
        $old = Product::factory()->create();
        $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        $fresh = Product::factory()->create();

        $this->getJson('/api/admin/products?updated_since='.now()->subDay()->toDateString(), $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fresh->id);
    }
    // ------------------------------------------------- slug et relecture IA

    public function test_a_new_product_is_not_validated_yet(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'name' => 'Cible ronde fluo',
            'description' => '<p>Une cible.</p>',
            'category_id' => $this->category()->id,
            'price' => 4.9,
        ], $this->headers())->assertCreated();

        $response->assertJsonPath('data.ai_validated', false);
        $this->assertDatabaseHas('products', ['slug' => 'cible-ronde-fluo', 'ai_validated' => false]);
    }

    public function test_the_validation_flag_can_be_set_and_cleared(): void
    {
        $product = Product::factory()->create(['ai_validated' => false]);

        $this->patchJson('/api/admin/products/'.$product->id, ['ai_validated' => true], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.ai_validated', true);

        $this->patchJson('/api/admin/products/'.$product->id, ['ai_validated' => false], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.ai_validated', false);
    }

    public function test_the_flag_refuses_anything_but_a_boolean(): void
    {
        $product = Product::factory()->create();

        $this->patchJson('/api/admin/products/'.$product->id, ['ai_validated' => 'maybe'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_validated']);
    }

    public function test_the_slug_can_be_changed(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien-slug']);

        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'cible-ronde-10cm'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.slug', 'cible-ronde-10cm');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'cible-ronde-10cm']);
    }

    public function test_a_slug_stays_unique(): void
    {
        Product::factory()->create(['slug' => 'deja-pris']);
        $product = Product::factory()->create(['slug' => 'le-mien']);

        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'deja-pris'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_a_product_keeps_its_own_slug_without_complaint(): void
    {
        $product = Product::factory()->create(['slug' => 'le-mien']);

        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'le-mien'], $this->headers())
            ->assertOk();
    }

    public function test_a_slug_must_look_like_one(): void
    {
        $product = Product::factory()->create();

        foreach (['Cible Ronde', 'cible ronde', 'cible_ronde', 'cible--ronde', '-cible', 'cible-'] as $wrong) {
            $this->patchJson('/api/admin/products/'.$product->id, ['slug' => $wrong], $this->headers())
                ->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        }
    }

    public function test_a_created_product_can_carry_its_own_slug(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => 'Cible ronde fluo',
            'description' => '<p>Une cible.</p>',
            'category_id' => $this->category()->id,
            'price' => 4.9,
            'slug' => 'cible-ronde-choisie',
        ], $this->headers())->assertCreated()->assertJsonPath('data.slug', 'cible-ronde-choisie');
    }

    public function test_a_slug_retired_by_another_product_cannot_be_taken(): void
    {
        $other = Product::factory()->create(['slug' => 'ancien-du-voisin']);
        $other->update(['slug' => 'nouveau-du-voisin']);

        $product = Product::factory()->create(['slug' => 'le-mien']);

        // L'ancienne adresse du voisin redirige encore : la reprendre
        // enverrait ses vieux liens sur le mauvais article.
        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'ancien-du-voisin'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_a_product_may_return_to_its_own_old_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'premier']);

        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'second'], $this->headers())
            ->assertOk();

        $this->patchJson('/api/admin/products/'.$product->id, ['slug' => 'premier'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.slug', 'premier');
    }

    public function test_a_created_slug_avoids_retired_ones(): void
    {
        $other = Product::factory()->create(['slug' => 'cible-ronde-fluo']);
        $other->update(['slug' => 'autre-chose']);

        $this->postJson('/api/admin/products', [
            'name' => 'Cible ronde fluo',
            'description' => '<p>Une cible.</p>',
            'category_id' => $this->category()->id,
            'price' => 4.9,
        ], $this->headers())->assertCreated()->assertJsonPath('data.slug', 'cible-ronde-fluo-2');
    }
}
