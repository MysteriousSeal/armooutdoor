<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un produit ne doit tenir qu'une ligne dans le panier.
 *
 * L'index unique portait sur (user_id, product_id, product_variant_id), ce
 * qui ne couvrait pas les produits vendus sans déclinaison : en SQL, NULL
 * n'est pas égal à NULL, donc deux lignes à NULL sont deux clés distinctes.
 * Un double clic sur « ajouter au panier » laissait alors le même article
 * deux fois, chacun avec sa quantité.
 */
class CartDuplicateRowTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): Cart
    {
        return app(Cart::class);
    }

    public function test_the_database_refuses_a_second_row_without_a_variant(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]);

        // Le cœur du sujet : c'est la base qui doit refuser, pas seulement le
        // code applicatif.
        $this->expectException(UniqueConstraintViolationException::class);

        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]);
    }

    public function test_a_rapid_double_submit_leaves_one_line(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($user);

        // Deux requêtes qui se suivent de près, comme un double clic.
        $this->cart()->update($product, 1);
        $this->cart()->update($product, 1);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(1, CartItem::query()->first()->quantity);
    }

    public function test_the_loser_of_the_race_recovers_instead_of_failing(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($user);

        // La ligne apparaît entre la lecture et l'écriture : updateOrCreate
        // croit devoir insérer, et se heurte à l'index. Une seule injection,
        // sinon l'écoute se déclenche sur ses propres requêtes.
        $injected = false;

        DB::listen(function ($query) use ($user, $product, &$injected): void {
            if ($injected || ! str_contains($query->sql, 'select * from "cart_items"')) {
                return;
            }

            $injected = true;

            DB::table('cart_items')->insert([
                'user_id' => $user->id, 'product_id' => $product->id,
                'product_variant_id' => null, 'quantity' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        // updateOrCreate rattrape lui-même la violation et relit la ligne
        // gagnante : encore faut-il que la base refuse la seconde insertion,
        // ce qu'elle ne faisait pas.
        $this->cart()->update($product, 3);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(3, CartItem::query()->first()->quantity, 'la quantité demandée est perdue');
    }

    public function test_two_variants_of_a_product_remain_two_lines(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 0]);

        $variants = collect(['S', 'M'])->map(fn (string $label): ProductVariant => ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $label, 'fr' => $label],
            'sku' => 'V-'.$label,
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]));

        $this->actingAs($user);

        // L'index ne doit pas confondre deux tailles du même article.
        foreach ($variants as $variant) {
            $this->cart()->update($product, 1, $variant);
        }

        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_a_variant_line_and_a_plain_line_can_coexist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 5]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // COALESCE(variant_id, 0) ne doit pas percuter une vraie déclinaison :
        // aucun identifiant ne vaut zéro.
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]);
        CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_two_customers_keep_their_own_line(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        foreach (User::factory()->count(2)->create() as $user) {
            CartItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]);
        }

        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_updating_a_quantity_still_works(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($user);
        $this->cart()->update($product, 1);
        $this->cart()->update($product, 4);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(4, CartItem::query()->first()->quantity);
    }

    public function test_removing_a_line_still_works(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($user);
        $this->cart()->update($product, 2);
        $this->cart()->update($product, 0);

        $this->assertSame(0, CartItem::query()->count());
    }
}
