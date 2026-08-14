<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        User::factory()->create(['name' => 'Jane Shopper', 'email' => 'jane@example.com']);

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('Jane Shopper')
            ->assertSee('jane@example.com')
            ->assertDontSee('admin@armooutdoor.test');

        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('Tente crête deux places')
            ->assertSee('Abris');
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

        Storage::fake('public');

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
    }
}
