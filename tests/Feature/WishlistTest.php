<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_wishlist(): void
    {
        $this->get('/account/wishlist')->assertRedirect('/login');
    }

    public function test_guests_cannot_add_to_the_wishlist(): void
    {
        $product = Product::factory()->create();

        $this->post('/wishlist', ['product_id' => $product->id])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_a_user_can_add_a_product_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post('/wishlist', ['product_id' => $product->id])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->get('/account/wishlist')
            ->assertOk()
            ->assertSee($product->localizedName());
    }

    public function test_adding_the_same_product_twice_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post('/wishlist', ['product_id' => $product->id]);
        $this->actingAs($user)->post('/wishlist', ['product_id' => $product->id]);

        $this->assertSame(1, WishlistItem::query()->where('user_id', $user->id)->count());
    }

    public function test_adding_an_unknown_product_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/products/does-not-exist')
            ->post('/wishlist', ['product_id' => 999999])
            ->assertSessionHasErrors('product_id');
    }

    public function test_a_user_can_remove_a_product_from_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)
            ->delete('/wishlist/'.$product->slug)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_a_user_only_sees_their_own_wishlist_items(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $myProduct = Product::factory()->create();
        $theirProduct = Product::factory()->create();

        WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $myProduct->id]);
        WishlistItem::query()->create(['user_id' => $stranger->id, 'product_id' => $theirProduct->id]);

        $this->actingAs($user)
            ->get('/account/wishlist')
            ->assertOk()
            ->assertSee($myProduct->localizedName())
            ->assertDontSee($theirProduct->localizedName());
    }

    public function test_inactive_products_do_not_appear_in_the_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);
        WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)
            ->get('/account/wishlist')
            ->assertOk()
            ->assertDontSee($product->localizedName());
    }

    public function test_removing_another_users_wishlist_item_does_not_affect_it(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $product = Product::factory()->create();
        WishlistItem::query()->create(['user_id' => $stranger->id, 'product_id' => $product->id]);

        $this->actingAs($user)->delete('/wishlist/'.$product->slug);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $stranger->id,
            'product_id' => $product->id,
        ]);
    }
}
