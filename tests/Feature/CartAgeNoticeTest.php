<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The basket says what will be asked before it is asked.
 *
 * It never blocks an order: a customer with no proof at all can still buy a
 * restricted article, and is told a proof will be wanted before it ships.
 */
class CartAgeNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(bool $restricted): Product
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'age_restricted' => $restricted,
            'quantity' => 5,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();

        return $product;
    }

    private function document(User $user, string $status, ?string $expires = null): IdentityDocument
    {
        return tap(IdentityDocument::query()->create([
            'user_id' => $user->id,
            'kind' => 'passport',
            'original_name' => 'p.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'path' => 'identity-documents/x.enc',
            'status' => $status,
        ]), fn ($d) => $d->forceFill([
            'expires_at' => $expires,
            'reviewed_at' => $status === 'pending' ? null : now(),
        ])->save());
    }

    public function test_an_ordinary_basket_says_nothing_about_age(): void
    {
        $this->cartWith(false);

        $this->get('/cart')->assertOk()->assertDontSee('cart-age', false);
    }

    public function test_a_guest_is_told_what_will_be_asked_and_where_to_go(): void
    {
        $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('cart-age--guest', false)
            ->assertSee(__('store.cart_age_guest'))
            ->assertSee(route('login'), false);
    }

    public function test_one_article_is_named_in_the_singular(): void
    {
        $product = $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('Un article de votre panier est réservé aux majeurs')
            ->assertSee('Article concerné')
            ->assertSee('<li data-product-id="'.$product->id.'">'.e($product->localizedName()).'</li>', false);
    }

    public function test_several_articles_are_listed_in_the_plural(): void
    {
        // The names carry commas of their own, so two of them strung together
        // read as four.
        $first = $this->cartWith(true);
        $second = $this->cartWith(true);

        $page = $this->get('/cart')->assertOk();

        $page->assertSee('Plusieurs articles de votre panier sont réservés aux majeurs');
        $page->assertSee('Articles concernés');
        $page->assertSee('<li data-product-id="'.$first->id.'">'.e($first->localizedName()).'</li>', false);
        $page->assertSee('<li data-product-id="'.$second->id.'">'.e($second->localizedName()).'</li>', false);
    }

    public function test_an_unrestricted_article_stays_out_of_the_list(): void
    {
        $restricted = $this->cartWith(true);
        $ordinary = $this->cartWith(false);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('<li data-product-id="'.$restricted->id.'">'.e($restricted->localizedName()).'</li>', false)
            ->assertDontSee('<li data-product-id="'.$ordinary->id.'">'.e($ordinary->localizedName()).'</li>', false);
    }

    public function test_a_customer_with_nothing_sent_is_pointed_at_the_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('cart-age--none', false)
            ->assertSee(route('account.documents.index'), false);
    }

    public function test_a_verified_customer_is_told_how_long_it_holds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->document($user, 'verified', now()->addYears(2)->toDateString());
        $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('cart-age--verified', false)
            ->assertSee(now()->addYears(2)->translatedFormat('d F Y'), false)
            // Nothing to do, so nothing to click.
            ->assertDontSee('cart-age-cta', false);
    }

    public function test_a_document_being_looked_at_says_so(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->document($user, 'pending');
        $this->cartWith(true);

        $this->get('/cart')->assertOk()->assertSee('cart-age--pending', false);
    }

    public function test_a_lapsed_proof_asks_for_a_new_one_without_barring_the_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->document($user, 'verified', now()->subMonth()->toDateString());
        $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('cart-age--expired', false)
            ->assertSee(__('store.cart_age_expired_cta'))
            // The order goes through regardless: the proof is wanted before
            // dispatch, not before payment.
            ->assertSee(route('checkout.show'), false);
    }

    public function test_a_removed_article_takes_its_entry_with_it(): void
    {
        // The line goes without a reload, so the notice has to be able to find
        // its own entry for that product.
        $first = $this->cartWith(true);
        $second = $this->cartWith(true);

        $page = $this->get('/cart')->assertOk();

        $page->assertSee('data-product-id="'.$first->id.'"', false);
        $page->assertSee('data-product-id="'.$second->id.'"', false);
        $page->assertSee('data-cart-age', false);
    }

    public function test_the_notice_carries_both_plural_forms(): void
    {
        // Rewritten in the browser after a removal, so it cannot go back to
        // the server for the wording.
        $this->cartWith(true);
        $this->cartWith(true);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('data-title-one="'.e(trans_choice('store.cart_age_title', 1)).'"', false)
            ->assertSee('data-title-many="'.e(trans_choice('store.cart_age_title', 2)).'"', false)
            ->assertSee('data-label-one="'.e(trans_choice('store.cart_age_items', 1)).'"', false);
    }

    public function test_removing_the_last_restricted_article_clears_the_notice(): void
    {
        // The server side of the same question: reloaded, it must agree with
        // what the browser did.
        $product = $this->cartWith(true);

        $this->get('/cart')->assertOk()->assertSee('data-cart-age', false);

        $this->delete('/cart/'.$product->slug)->assertRedirect();

        $this->get('/cart')->assertOk()->assertDontSee('data-cart-age', false);
    }
}
