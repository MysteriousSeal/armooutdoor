<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagination numérotée de la liste des commandes.
 *
 * simplePaginate() ne compte jamais le total : il ne peut donc ni numéroter
 * les pages, ni dire combien il y a de commandes. La liste n'offrait que
 * Précédent / Suivant, sans moyen de sauter ni de savoir où l'on en est.
 */
class OrderPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function orders(int $count, array $attributes = []): void
    {
        foreach (range(1, $count) as $i) {
            Order::query()->create([
                'number' => Order::generateNumber(),
                'user_id' => User::factory()->create()->id,
                'status' => 'placed',
                'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
                'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
                'carrier_method' => 'home',
                'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
                'subtotal_cents' => 1000,
                'shipping_cents' => 0,
                'discount_cents' => 0,
                'total_cents' => 1000,
                'payment_method' => 'card',
                ...$attributes,
            ]);
        }
    }

    public function test_the_list_knows_how_many_pages_there_are(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(45);

        $orders = $this->actingAs($admin)->get('/admin/orders')->assertOk()->viewData('orders');

        // simplePaginate ne sait rien de tout cela.
        $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
        $this->assertSame(45, $orders->total());
        $this->assertSame(3, $orders->lastPage());
        $this->assertCount(20, $orders);
    }

    public function test_the_count_line_states_the_total(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(45);

        $this->actingAs($admin)
            ->get('/admin/orders?page=2')
            ->assertOk()
            ->assertSee('Showing 21–40 of 45');
    }

    public function test_page_numbers_are_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(45);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('admin-pagination-page', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_no_pager_when_everything_fits_on_one_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(12);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertDontSee('admin-pagination-page', false);
    }

    public function test_a_long_list_shows_a_window_not_every_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(220);

        // Onze pages, on est en page 6 : la fenêtre couvre 4 à 8, plus la
        // première et la dernière. Les pages 2, 3, 9 et 10 doivent disparaître,
        // sinon la fenêtre ne sert à rien.
        $html = $this->actingAs($admin)->get('/admin/orders?page=6')->assertOk()->getContent();

        foreach ([4, 5, 7, 8] as $shown) {
            $this->assertStringContainsString('page='.$shown.'"', $html, 'page '.$shown.' devrait être dans la fenêtre');
        }

        foreach ([2, 3, 9, 10] as $hidden) {
            $this->assertStringNotContainsString('page='.$hidden.'"', $html, 'page '.$hidden.' devrait être hors fenêtre');
        }

        $this->assertStringContainsString('admin-pagination-gap', $html);
    }

    public function test_the_first_and_last_pages_stay_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(220);

        $this->actingAs($admin)
            ->get('/admin/orders?page=6')
            ->assertOk()
            ->assertSee('page=1', false)
            ->assertSee('page=11', false);
    }

    public function test_no_product_is_shown_twice_or_skipped(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(45);

        $seen = collect();

        foreach ([1, 2, 3] as $page) {
            $seen = $seen->concat(
                $this->actingAs($admin)->get('/admin/orders?page='.$page)->viewData('orders')->pluck('id')
            );
        }

        $this->assertCount(45, $seen);
        $this->assertCount(45, $seen->unique());
    }

    public function test_the_filters_survive_in_the_pager_links(): void
    {
        $admin = User::factory()->admin()->create();
        $this->orders(45, ['status' => 'shipped']);

        // Sans withQueryString, passer en page 2 perdrait le filtre et
        // afficherait des commandes que la page 1 excluait.
        $this->actingAs($admin)
            ->get('/admin/orders?status=shipped')
            ->assertOk()
            ->assertSee('status=shipped', false);
    }

    public function test_the_other_admin_lists_are_untouched(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin/customers', '/admin/conversations', '/admin/activity'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }
}
