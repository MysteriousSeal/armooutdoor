<?php

namespace Tests\Feature\Admin;

use App\Models\Marketplace;
use App\Models\Product;
use App\Models\User;
use App\Models\VintedItem;
use App\Support\ImageThumbnailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The articles sold on Vinted only.
 *
 * A list kept by hand: a photo, a title, what the lot cost and how many are
 * left. What it has to hold: nothing of it becomes a product, a sale takes
 * one piece off, and a sold-out item changes tab.
 */
class VintedItemTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $uploaded = [];

    protected function tearDown(): void
    {
        foreach ($this->uploaded as $image) {
            @unlink(public_path('images/'.$image));
            @unlink(ImageThumbnailer::absoluteThumbnailPath($image));
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** An item as the form leaves it: its totals, and the first lot under them. */
    private function item(array $attributes = []): VintedItem
    {
        $item = VintedItem::query()->create($attributes + [
            'title' => 'Veste M65 olive',
            'image' => 'vinted-items/kept.webp',
            'purchase_total_cents' => 6000,
            'lot_quantity' => 4,
            'quantity' => 4,
        ]);

        $item->lots()->create(['quantity' => $item->lot_quantity, 'purchase_total_cents' => $item->purchase_total_cents]);

        return $item;
    }

    public function test_the_marketplaces_page_opens_onto_the_vinted_list(): void
    {
        Marketplace::query()->firstOrCreate(['name' => 'Vinted']);
        $this->item();
        $this->item(['title' => 'Parti', 'quantity' => 0]);

        $this->actingAs($this->admin())
            ->get(route('admin.marketplaces.index'))
            ->assertOk()
            ->assertSee(route('admin.marketplaces.vinted'), false)
            // Only what is left to sell is counted on the card.
            ->assertSee('1</span>', false)
            ->assertSee('item to sell');
    }

    public function test_an_item_is_added_with_its_image_and_starts_whole(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.marketplaces.vinted.items.store'), [
                'title' => 'Sac à dos 40 L',
                'image' => UploadedFile::fake()->image('sac.jpg', 1200, 1200),
                'purchase_total' => '45.50',
                'quantity' => 3,
            ])
            ->assertRedirect(route('admin.marketplaces.vinted'));

        $item = VintedItem::query()->sole();
        $this->uploaded[] = $item->image;

        $this->assertSame('Sac à dos 40 L', $item->title);
        $this->assertSame(4550, $item->purchase_total_cents);
        $this->assertSame(3, $item->lot_quantity);
        $this->assertSame(3, $item->quantity);
        $this->assertFileExists(public_path('images/'.$item->image));

        // The purchase opens the history as its first lot.
        $lot = $item->lots()->sole();
        $this->assertSame([3, 4550], [$lot->quantity, $lot->purchase_total_cents]);

        // The price is the lot's: one piece cost a third of it.
        $this->assertSame(1517, $item->unitCostCents());
    }

    public function test_an_item_cannot_be_added_without_an_image(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.marketplaces.vinted.items.store'), [
                'title' => 'Sans photo',
                'purchase_total' => '10',
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, VintedItem::query()->count());
    }

    public function test_adding_an_item_creates_no_product(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.marketplaces.vinted.items.store'), [
                'title' => 'Jamais en boutique',
                'image' => UploadedFile::fake()->image('piece.jpg', 1200, 1200),
                'purchase_total' => '10',
                'quantity' => 1,
            ]);

        $this->uploaded[] = VintedItem::query()->sole()->image;

        // The storefront reads `products` and nothing else: no row there, no
        // way onto the shop.
        $this->assertSame(0, Product::query()->count());
    }

    public function test_sold_is_pressed_from_the_item_page_not_from_the_list(): void
    {
        $item = $this->item();
        $admin = $this->admin();
        $sold = route('admin.marketplaces.vinted.items.sold', $item);

        // In the list a row is one press away from a wrong count.
        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted'))
            ->assertOk()
            ->assertDontSee($sold, false);

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted.items.edit', $item))
            ->assertOk()
            ->assertSee($sold, false);
    }

    public function test_a_sold_out_item_offers_no_sold_button(): void
    {
        $item = $this->item(['quantity' => 0]);

        $this->actingAs($this->admin())
            ->get(route('admin.marketplaces.vinted.items.edit', $item))
            ->assertOk()
            ->assertDontSee(route('admin.marketplaces.vinted.items.sold', $item), false);
    }

    public function test_sold_takes_one_piece_off_and_keeps_the_unit_cost(): void
    {
        $item = $this->item();

        $this->actingAs($this->admin())
            ->from(route('admin.marketplaces.vinted'))
            ->post(route('admin.marketplaces.vinted.items.sold', $item), ['price' => '25'])
            ->assertRedirect(route('admin.marketplaces.vinted'))
            ->assertSessionHas('status');

        $item->refresh();

        $this->assertSame(3, $item->quantity);
        $this->assertSame(4, $item->lot_quantity);
        // 60 € for four: still 15 € a piece with three left.
        $this->assertSame(1500, $item->unitCostCents());

        // The sale is on record with its price: 25 € for a piece that cost 15.
        $sale = $item->sales()->sole();
        $this->assertSame(2500, $sale->price_cents);
        $this->assertSame(1000, $item->marginCents($sale));
        $this->assertSame([2500, 1000], [$item->soldTotalCents(), $item->profitCents()]);
    }

    public function test_the_last_piece_sold_moves_the_item_to_the_sold_out_tab(): void
    {
        $item = $this->item(['quantity' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.marketplaces.vinted.items.sold', $item), ['price' => '25']);

        $this->assertTrue($item->fresh()->isSoldOut());

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted'))
            ->assertOk()
            // By its edit link, not its name: the flash still names it.
            ->assertDontSee(route('admin.marketplaces.vinted.items.edit', $item), false);

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted', ['tab' => 'sold-out']))
            ->assertOk()
            ->assertSee('Veste M65 olive')
            // Nothing left to take off: the button is gone with the stock.
            ->assertDontSee(route('admin.marketplaces.vinted.items.sold', $item), false);
    }

    public function test_sold_never_goes_below_zero(): void
    {
        $item = $this->item(['quantity' => 0]);

        $this->actingAs($this->admin())
            ->post(route('admin.marketplaces.vinted.items.sold', $item), ['price' => '25'])
            ->assertSessionHasErrors('vinted');

        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_a_sale_needs_its_price(): void
    {
        $item = $this->item();

        $this->actingAs($this->admin())
            ->post(route('admin.marketplaces.vinted.items.sold', $item), [])
            ->assertSessionHasErrors('price');

        $this->assertSame(4, $item->fresh()->quantity);
        $this->assertSame(0, $item->sales()->count());
    }

    public function test_removing_a_sale_puts_its_piece_back(): void
    {
        $item = $this->item(['quantity' => 2]);
        $kept = $item->sales()->create(['price_cents' => 2000]);
        $mistake = $item->sales()->create(['price_cents' => 900]);

        $this->actingAs($this->admin())
            ->delete(route('admin.marketplaces.vinted.items.sales.destroy', [$item, $mistake]))
            ->assertRedirect(route('admin.marketplaces.vinted.items.edit', $item));

        $item->refresh();

        $this->assertSame(3, $item->quantity);
        $this->assertSame([$kept->id], $item->sales()->pluck('id')->all());
        // What was bought has not moved.
        $this->assertSame([4, 6000], [$item->lot_quantity, $item->purchase_total_cents]);
    }

    public function test_a_sale_is_only_removed_through_its_own_item(): void
    {
        $item = $this->item();
        $other = $this->item(['title' => 'Autre', 'quantity' => 3]);
        $sale = $other->sales()->create(['price_cents' => 1000]);

        $this->actingAs($this->admin())
            ->delete(route('admin.marketplaces.vinted.items.sales.destroy', [$item, $sale]))
            ->assertNotFound();

        $this->assertSame(3, $other->fresh()->quantity);
    }

    public function test_a_sale_without_a_price_counts_for_nothing_in_the_figures(): void
    {
        // One reported before prices were asked for, one at a loss.
        $item = $this->item(['quantity' => 2]);
        $item->sales()->create(['price_cents' => null]);
        $item->sales()->create(['price_cents' => 1200]);
        $admin = $this->admin();

        $this->assertSame(2, $item->soldCount());
        $this->assertSame(1200, $item->soldTotalCents());
        // 12 € for a piece that cost 15: three euros lost, and the unpriced
        // sale adds neither income nor cost.
        $this->assertSame(-300, $item->profitCents());

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted.items.edit', $item))
            ->assertOk()
            ->assertSee('Sold price')
            ->assertSee('vinted-item-loss', false);

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted'))
            ->assertOk()
            ->assertSee('Profit')
            ->assertSee('2 sold');
    }

    public function test_adding_a_lot_raises_the_totals_and_averages_the_cost(): void
    {
        $item = $this->item(['quantity' => 0]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.marketplaces.vinted.items.lots.store', $item), [
                'quantity' => 2,
                'purchase_total' => '42',
            ])
            ->assertRedirect(route('admin.marketplaces.vinted.items.edit', $item));

        $item->refresh();

        // Sold out before, back on sale with the two new pieces.
        $this->assertSame(2, $item->quantity);
        $this->assertSame(6, $item->lot_quantity);
        $this->assertSame(10200, $item->purchase_total_cents);
        // 60 € for four, then 42 € for two: 102 € over six pieces.
        $this->assertSame(1700, $item->unitCostCents());
        $this->assertSame(2, $item->lots()->count());

        $this->actingAs($admin)
            ->get(route('admin.marketplaces.vinted'))
            ->assertSee(route('admin.marketplaces.vinted.items.edit', $item), false);
    }

    public function test_saving_the_form_cannot_change_the_price_or_the_quantities(): void
    {
        $item = $this->item(['quantity' => 3]);

        $this->actingAs($this->admin())
            ->put(route('admin.marketplaces.vinted.items.update', $item), [
                'title' => 'Veste M65 kaki',
                'purchase_total' => '1',
                'lot_quantity' => 99,
                'quantity' => 99,
            ])
            ->assertRedirect(route('admin.marketplaces.vinted.items.edit', $item));

        $item->refresh();

        $this->assertSame('Veste M65 kaki', $item->title);
        // No new file was sent: the image on record stays.
        $this->assertSame('vinted-items/kept.webp', $item->image);
        $this->assertSame([6000, 4, 3], [$item->purchase_total_cents, $item->lot_quantity, $item->quantity]);
    }

    public function test_the_vinted_link_is_saved_and_shown_on_the_page_and_the_list(): void
    {
        $item = $this->item();
        $admin = $this->admin();
        $link = 'https://www.vinted.fr/items/1234567890-veste-m65';

        $this->actingAs($admin)
            ->put(route('admin.marketplaces.vinted.items.update', $item), [
                'title' => 'Veste M65 olive',
                'vinted_url' => $link,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($link, $item->fresh()->vinted_url);

        foreach ([route('admin.marketplaces.vinted.items.edit', $item), route('admin.marketplaces.vinted')] as $page) {
            $this->actingAs($admin)
                ->get($page)
                ->assertOk()
                ->assertSee('href="'.$link.'"', false)
                ->assertSee('View on Vinted');
        }

        // Emptied, the link goes: the item is no longer posted.
        $this->actingAs($admin)
            ->put(route('admin.marketplaces.vinted.items.update', $item), [
                'title' => 'Veste M65 olive',
                'vinted_url' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()->vinted_url);
    }

    public function test_a_link_that_is_not_on_vinted_is_refused(): void
    {
        $item = $this->item();

        foreach ([
            'https://www.leboncoin.fr/ad/123',
            'https://vinted.fr.example.com/items/1',
            'https://notvinted.fr/items/1',
            'http://www.vinted.fr/items/1',
            'javascript:alert(1)',
        ] as $link) {
            $this->actingAs($this->admin())
                ->put(route('admin.marketplaces.vinted.items.update', $item), [
                    'title' => 'Veste M65 olive',
                    'vinted_url' => $link,
                ])
                ->assertSessionHasErrors('vinted_url');
        }

        $this->assertNull($item->fresh()->vinted_url);

        // The other country sites are Vinted too.
        foreach (['https://www.vinted.co.uk/items/1', 'https://vinted.com/items/1', 'https://www.vinted.be/items/1'] as $link) {
            $this->actingAs($this->admin())
                ->put(route('admin.marketplaces.vinted.items.update', $item), [
                    'title' => 'Veste M65 olive',
                    'vinted_url' => $link,
                ])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_the_add_form_does_not_ask_for_the_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.marketplaces.vinted.items.create'))
            ->assertOk()
            ->assertDontSee('name="vinted_url"', false);
    }

    public function test_a_lot_entered_by_mistake_is_taken_back_off(): void
    {
        $item = $this->item();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.marketplaces.vinted.items.lots.store', $item), [
            'quantity' => 5,
            'purchase_total' => '50',
        ]);

        $lot = $item->lots()->get()->last();

        $this->actingAs($admin)
            ->delete(route('admin.marketplaces.vinted.items.lots.destroy', [$item, $lot]))
            ->assertRedirect(route('admin.marketplaces.vinted.items.edit', $item));

        $item->refresh();

        $this->assertSame([6000, 4, 4], [$item->purchase_total_cents, $item->lot_quantity, $item->quantity]);
        $this->assertSame(1, $item->lots()->count());
    }

    public function test_the_first_lot_cannot_be_removed(): void
    {
        $item = $this->item();

        $this->actingAs($this->admin())
            ->delete(route('admin.marketplaces.vinted.items.lots.destroy', [$item, $item->lots()->first()]))
            ->assertSessionHasErrors('vinted');

        $this->assertSame(1, $item->lots()->count());
        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_a_lot_whose_pieces_have_sold_cannot_be_removed(): void
    {
        // Nine bought in two lots, three left: the five of the second lot
        // are no longer all there to take back.
        $item = $this->item(['purchase_total_cents' => 11000, 'lot_quantity' => 9, 'quantity' => 3]);
        $lot = $item->lots()->create(['quantity' => 5, 'purchase_total_cents' => 5000]);

        $this->actingAs($this->admin())
            ->delete(route('admin.marketplaces.vinted.items.lots.destroy', [$item, $lot]))
            ->assertSessionHasErrors('vinted');

        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertNotNull($lot->fresh());
    }

    public function test_a_lot_is_only_removed_through_its_own_item(): void
    {
        $item = $this->item();
        $other = $this->item(['title' => 'Autre']);
        $lot = $other->lots()->create(['quantity' => 1, 'purchase_total_cents' => 100]);

        $this->actingAs($this->admin())
            ->delete(route('admin.marketplaces.vinted.items.lots.destroy', [$item, $lot]))
            ->assertNotFound();
    }

    public function test_removing_an_item_deletes_its_image_too(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.marketplaces.vinted.items.store'), [
                'title' => 'À retirer',
                'image' => UploadedFile::fake()->image('retire.jpg', 1200, 1200),
                'purchase_total' => '10',
                'quantity' => 1,
            ]);

        $item = VintedItem::query()->sole();
        $this->uploaded[] = $item->image;
        $path = public_path('images/'.$item->image);

        $this->assertFileExists($path);

        $this->actingAs($admin)
            ->delete(route('admin.marketplaces.vinted.items.destroy', $item))
            ->assertRedirect(route('admin.marketplaces.vinted'));

        $this->assertSame(0, VintedItem::query()->count());
        $this->assertFileDoesNotExist($path);
    }

    public function test_the_edit_page_shows_the_figures_without_fields_for_them(): void
    {
        $item = $this->item(['quantity' => 3]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.marketplaces.vinted.items.edit', $item))
            ->assertOk()
            ->assertSee('Add stock')
            ->assertSee('per piece on average')
            ->getContent();

        // The only quantity and price fields are the ones that add a lot,
        // and they arrive empty.
        $this->assertSame(1, substr_count($html, 'name="quantity"'));
        $this->assertSame(1, substr_count($html, 'name="purchase_total"'));
        $this->assertStringNotContainsString('value="60.00"', $html);
    }
}
