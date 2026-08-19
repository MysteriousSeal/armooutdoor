<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, AdminSeeder::class]);
    }

    public function test_admin_login_page_is_public(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Admin sign in');
    }

    public function test_customers_cannot_open_the_admin_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_an_admin_can_sign_in_and_see_customers_and_products(): void
    {
        $this->from('/admin')
            ->post('/admin/login', [
                'email' => 'admin@armooutdoor.test',
                'password' => 'password',
            ])
            ->assertRedirect('/admin/dashboard');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Products');

        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Shopper',
            'email' => 'jane@example.com',
        ]);

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('Jane SHOPPER')
            ->assertSee('jane@example.com')
            ->assertSee('With orders')
            ->assertSee('No orders')
            ->assertSee('Joined between')
            ->assertDontSee('admin@armooutdoor.test');

        $this->get('/admin/products?sort=id-asc')
            ->assertOk()
            ->assertSee('Tente crête deux places')
            ->assertSee('Abris');
    }

    public function test_admin_products_default_to_id_descending(): void
    {
        $admin = User::factory()->admin()->create();
        $cell = fn (int $id): string => '<strong>'.$id.'</strong>';
        $highestIds = Product::query()->orderByDesc('id')->limit(3)->pluck('id')->map($cell)->all();
        $lowestIds = Product::query()->orderBy('id')->limit(3)->pluck('id')->map($cell)->all();

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSeeInOrder($highestIds, false)
            ->assertSee('sort=id-asc', false);

        $this->actingAs($admin)
            ->get('/admin/products?sort=id-asc')
            ->assertOk()
            ->assertSeeInOrder($lowestIds, false);
    }

    public function test_admin_products_list_shows_variant_subtable(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->where('slug', 'shelters')->firstOrFail();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => ['en' => 'Variant parent', 'fr' => 'Produit à variantes'],
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'VAR-M',
            'gtin' => '1234567890123',
            'price_cents' => 1999,
            'quantity' => 1,
            'is_active' => true,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'L']],
            'sku' => 'VAR-L',
            'price_cents' => 1999,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('admin-variant-panel', false)
            ->assertSeeInOrder(['M', 'VAR-M', '1234567890123', '1 left', 'L', 'VAR-L', 'Out'], false);
    }

    public function test_admin_login_rejects_store_customers(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->from('/admin')
            ->post('/admin/login', [
                'email' => 'jane@example.com',
                'password' => 'secret-pass',
            ])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('email');
    }

    public function test_an_admin_can_create_and_edit_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->where('slug', 'shelters')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Add product');

        // Product image uploads are written straight to public/images (not
        // through the Storage facade, so Storage::fake() can't intercept
        // them) — the real file is deleted at the end of this test so the
        // repo's public folder isn't left polluted with test fixtures.
        $response = $this->actingAs($admin)
            ->from('/admin/products/create')
            ->post('/admin/products', [
                'name' => 'Bivy forêt',
                'description' => 'Un bivy léger pour les bois mouillés.',
                'category_id' => $category->id,
                'price' => '89.50',
                'quantity' => 12,
                'image_file' => UploadedFile::fake()->image('forest-bivy.jpg', 800, 800),
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::query()->where('slug', 'bivy-foret')->firstOrFail();
        $this->assertSame(8950, $product->price_cents);
        $this->assertSame(12, $product->quantity);
        $this->assertSame('Bivy forêt', $product->name['fr']);

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Bivy forêt');

        $this->actingAs($admin)
            ->put('/admin/products/'.$product->id, [
                'name' => 'Bivy forêt II',
                'description' => 'Un bivy léger pour les bois mouillés.',
                'category_id' => $category->id,
                'price' => '99.00',
                'quantity' => 4,
            ])
            ->assertRedirect('/admin/products/'.$product->id.'/edit');

        $this->assertSame('Bivy forêt II', $product->fresh()->name['fr']);
        $this->assertSame(9900, $product->fresh()->price_cents);
        $this->assertSame(4, $product->fresh()->quantity);

        $image = $product->fresh()->image;
        @unlink(public_path('images/'.$image));
        @unlink(public_path('images/'.dirname($image).'/thumbs/'.basename($image)));
    }

    public function test_an_admin_can_search_customers_and_products(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Shopper',
            'email' => 'jane@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/admin/search')
            ->assertOk()
            ->assertSee('Type something above to search.');

        $this->actingAs($admin)
            ->get('/admin/search?q=jane')
            ->assertOk()
            ->assertSee('Jane SHOPPER')
            ->assertSee('jane@example.com')
            ->assertSee('View in customers');

        $this->actingAs($admin)
            ->get('/admin/search?q=tente')
            ->assertOk()
            ->assertSee('Tente crête deux places')
            ->assertSee('View in products');
    }
}
