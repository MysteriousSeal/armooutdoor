<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Cart;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_can_add_a_product_to_the_cart(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->from('/products/ridge-tent')
            ->post('/cart', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect('/products/ridge-tent')
            ->assertSessionHas('status');

        $this->get('/cart')
            ->assertOk()
            ->assertSee('Tente crête deux places')
            ->assertSee('698,00');
    }

    public function test_cart_quantities_can_be_updated_and_removed(): void
    {
        $product = Product::query()->where('slug', 'daylight-pack')->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->from('/cart')
            ->patch('/cart/daylight-pack', ['quantity' => 3])
            ->assertRedirect('/cart');

        $this->get('/cart')
            ->assertSee('477,00');

        $this->from('/cart')
            ->delete('/cart/daylight-pack')
            ->assertRedirect('/cart');

        $this->get('/cart')
            ->assertSee('Votre panier est vide.');
    }

    public function test_guest_cart_is_merged_when_creating_an_account(): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Client',
            'email' => 'jean@example.com',
            'password' => 'secret-pass',
            'terms' => '1',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => User::query()->where('email', 'jean@example.com')->value('id'),
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->get('/cart')
            ->assertSee('Poêle en fonte')
            ->assertSee('158,00');
    }

    public function test_signed_in_users_persist_cart_items(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'merino-field-shirt')->firstOrFail();

        $this->actingAs($user)
            ->post('/cart', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertSame(1, CartItem::query()->where('user_id', $user->id)->count());
    }

    public function test_adding_a_variant_product_without_selecting_a_variant_fails(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->from('/products/ridge-tent')
            ->post('/cart', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('variant_id');

        $this->assertSame(0, app(Cart::class)->quantity());
    }

    public function test_a_guest_can_add_a_specific_variant_to_the_cart(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();
        $variantM = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille M']],
            'price_cents' => 39900,
            'quantity' => 5,
            'is_active' => true,
        ]);
        $variantL = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'Taille L']],
            'price_cents' => 42900,
            'quantity' => 3,
            'is_active' => true,
        ]);

        $this->post('/cart', [
            'product_id' => $product->id,
            'variant_id' => $variantM->id,
            'quantity' => 2,
        ])->assertSessionHas('status');

        $this->post('/cart', [
            'product_id' => $product->id,
            'variant_id' => $variantL->id,
            'quantity' => 1,
        ])->assertSessionHas('status');

        $this->assertSame(3, app(Cart::class)->quantity());

        $this->get('/cart')
            ->assertOk()
            ->assertSee('Taille M')
            ->assertSee('Taille L')
            ->assertSee('798,00')
            ->assertSee('429,00');
    }

    public function test_french_cart_page_uses_translated_copy(): void
    {
        $product = Product::query()->where('slug', 'enamel-camp-kettle')->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('Votre panier')
            ->assertSee('Bouilloire de camp en émail');
    }
}
