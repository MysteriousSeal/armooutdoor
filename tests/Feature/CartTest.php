<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
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

        $this->from('/fr/products/ridge-tent')
            ->post('/fr/cart', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect('/fr/products/ridge-tent')
            ->assertSessionHas('status');

        $this->get('/fr/cart')
            ->assertOk()
            ->assertSee('Tente crête deux places')
            ->assertSee('698,00');
    }

    public function test_cart_quantities_can_be_updated_and_removed(): void
    {
        $product = Product::query()->where('slug', 'daylight-pack')->firstOrFail();

        $this->post('/fr/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->from('/fr/cart')
            ->patch('/fr/cart/daylight-pack', ['quantity' => 3])
            ->assertRedirect('/fr/cart');

        $this->get('/fr/cart')
            ->assertSee('477,00');

        $this->from('/fr/cart')
            ->delete('/fr/cart/daylight-pack')
            ->assertRedirect('/fr/cart');

        $this->get('/fr/cart')
            ->assertSee('Votre panier est vide.');
    }

    public function test_guest_cart_is_merged_when_creating_an_account(): void
    {
        $product = Product::query()->where('slug', 'cast-iron-skillet')->firstOrFail();

        $this->post('/fr/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->post('/fr/register', [
            'name' => 'Colas',
            'email' => 'colas@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect('/fr');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => User::query()->where('email', 'colas@example.com')->value('id'),
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->get('/fr/cart')
            ->assertSee('Poêle en fonte')
            ->assertSee('158,00');
    }

    public function test_signed_in_users_persist_cart_items(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'merino-field-shirt')->firstOrFail();

        $this->actingAs($user)
            ->post('/fr/cart', [
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

    public function test_french_cart_page_uses_translated_copy(): void
    {
        $product = Product::query()->where('slug', 'enamel-camp-kettle')->firstOrFail();

        $this->post('/fr/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->get('/fr/cart')
            ->assertOk()
            ->assertSee('Votre panier')
            ->assertSee('Bouilloire de camp en émail');
    }
}
