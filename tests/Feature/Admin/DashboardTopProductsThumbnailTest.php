<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les vignettes des meilleures ventes du tableau de bord, comme celles des
 * alertes de stock juste à côté.
 *
 * Une ligne sans visuel garde une tuile de même taille : la colonne de texte
 * doit rester alignée, sinon la liste paraît cassée.
 */
class DashboardTopProductsThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function soldProduct(array $productAttributes = [], int $quantity = 3): Product
    {
        $product = Product::factory()->create($productAttributes);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => ['fr' => 'Article vendu'],
            'image' => 'products/snapshot.webp',
            'unit_price_cents' => 1000,
            'quantity' => $quantity,
            'line_cents' => 1000 * $quantity,
        ]);

        return $product;
    }

    public function test_a_top_product_shows_its_current_image(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->soldProduct(['image' => 'products/live-image.webp']);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // L'image actuelle du produit, pas celle figée dans la ligne de
        // commande : le tableau de bord doit suivre la fiche produit.
        $this->assertStringContainsString($product->imageUrl(), $html);
        $this->assertStringNotContainsString('products/snapshot.webp', $html);
    }

    public function test_the_thumbnail_links_to_the_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->soldProduct(['image' => 'products/live-image.webp']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(route('admin.products.edit', $product), false);
    }

    public function test_a_product_without_an_image_keeps_an_empty_tile(): void
    {
        $admin = User::factory()->admin()->create();
        $this->soldProduct(['image' => '']);

        // La tuile tient la place de la vignette : sans elle le texte glisse
        // à gauche et la colonne se désaligne.
        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-stock-media is-empty', false);
    }

    public function test_a_deleted_product_keeps_an_empty_tile(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->soldProduct(['image' => 'products/live-image.webp']);
        $product->delete();

        // La ligne de commande survit au produit : elle doit rester lisible.
        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-stock-media is-empty', false)
            ->assertSee('Article vendu');
    }

    public function test_the_thumbnail_uses_the_same_class_as_stock_alerts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->soldProduct(['image' => 'products/live-image.webp', 'quantity' => 1]);

        // Les deux listes partagent le style : une classe différente les
        // ferait diverger à la première retouche.
        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'admin-stock-media'));
    }
}
