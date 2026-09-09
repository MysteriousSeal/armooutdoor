<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Models\VintedListing;
use App\Models\VintedListingImage;
use App\Support\ImageThumbnailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A product's Vinted listing.
 *
 * Nothing is sent to Vinted — the page composes and keeps the wording. What
 * it has to hold: a blank draft, one listing per product, and photos that
 * belong to it alone.
 */
class VintedListingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_product_page_offers_the_listing(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Vinted listing')
            ->assertSee(route('admin.products.vinted.edit', $product), false);
    }

    public function test_a_product_being_created_has_no_listing_to_offer(): void
    {
        // A listing attaches to a saved record: the creation form has no
        // product to hang one on yet.
        $this->actingAs($this->admin())
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertDontSee('Vinted listing');
    }

    public function test_an_untouched_listing_opens_completely_empty(): void
    {
        $product = Product::factory()->create([
            'name' => ['fr' => 'Cagoule camouflage'],
            'description' => ['fr' => '<p>Respirante et <b>légère</b>.</p>'],
            'price_cents' => 899,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.edit', $product))
            ->assertOk()
            // The product is named at the top of the page, to know what this
            // is about — but the fields themselves are empty.
            ->assertSee('Cagoule camouflage')
            ->getContent();

        // Both text fields arrive empty. The title is read off its attribute
        // rather than the absence of the name, which is in the header.
        $this->assertMatchesRegularExpression('#id="vinted-title".*?value=""#s', $html);
        $this->assertMatchesRegularExpression('#id="vinted-description".*?>\s*</textarea>#s', $html);
        $this->assertStringNotContainsString('Respirante et légère', $html);

        // The price is not carried over either: the shop's own reads beside
        // the field, it does not settle into it.
        $this->assertMatchesRegularExpression('#id="vinted-price".*?value=""#s', $html);
        $this->assertStringContainsString('Shop price', $html);

        // Opening the page writes nothing: until it is saved there is no
        // listing.
        $this->assertSame(0, VintedListing::query()->count());
    }

    public function test_saving_keeps_the_listing_against_the_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Cagoule camo — taille unique',
                'description' => "Portée deux fois.\nAucun défaut.",
                'price' => '12.50',
            ])
            ->assertRedirect(route('admin.products.vinted.edit', $product));

        $listing = $product->fresh()->vintedListing;

        $this->assertSame('Cagoule camo — taille unique', $listing->title);
        $this->assertSame("Portée deux fois.\nAucun défaut.", $listing->description);
        $this->assertSame(1250, $listing->price_cents);
    }

    public function test_saving_twice_edits_the_same_listing(): void
    {
        // One listing per product: two drafts would force every screen to
        // choose which to show.
        $product = Product::factory()->create();

        foreach (['Premier jet', 'Deuxième jet'] as $title) {
            $this->actingAs($this->admin())
                ->put(route('admin.products.vinted.update', $product), ['title' => $title]);
        }

        $this->assertSame(1, VintedListing::query()->where('product_id', $product->id)->count());
        $this->assertSame('Deuxième jet', $product->fresh()->vintedListing->title);
    }

    public function test_the_saved_stamp_is_in_english_like_the_rest_of_the_admin(): void
    {
        // The application locale is French, the shop's own. The back-office
        // is English throughout, and an "il y a une minute" in the middle of
        // an English page comes from there.
        $product = Product::factory()->create();
        VintedListing::query()->create(['product_id' => $product->id, 'title' => 'Écrite']);

        $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.edit', $product))
            ->assertOk()
            ->assertSee('Saved')
            ->assertSee('ago')
            ->assertDontSee('il y a');
    }

    public function test_a_listing_can_be_left_without_a_price(): void
    {
        // One writes the text one day and settles the price another.
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), ['title' => 'Sans prix', 'price' => null])
            ->assertRedirect();

        $this->assertNull($product->fresh()->vintedListing->price_cents);
    }

    public function test_photos_are_uploaded_kept_in_order_and_removed(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Avec photos',
                'images' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.jpg'),
                ],
            ])
            ->assertRedirect();

        $listing = $product->fresh()->vintedListing;
        $images = $listing->images()->get();

        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());
        // The files live apart from the catalogue's.
        $this->assertStringStartsWith('vinted/', $images->first()->image);

        $kept = $images->last();
        $dropped = $images->first();
        $droppedPath = public_path('images/'.$dropped->image);
        $droppedThumb = ImageThumbnailer::absoluteThumbnailPath($dropped->image);

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Avec photos',
                'remove_images' => [$dropped->id],
                'order' => [$kept->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $listing->images()->count());
        $this->assertFalse(is_file($droppedPath), 'the removed photo should leave no file behind');
        // The thumbnail goes with it: no row names it any more, so nothing
        // would sweep it later.
        $this->assertFalse(is_file($droppedThumb), 'the removed photo should take its thumbnail with it');

        $this->forget($listing);
    }

    /**
     * A photo keeps the shape it was shot in.
     *
     * Vinted shows the picture as it is and asks for a thousand pixels on the
     * smallest side. Squaring it, which is what the catalogue does to a
     * product shot, added transparent bands the marketplace renders in white
     * and threw the seller's framing away.
     */
    public function test_a_photo_keeps_its_proportions_with_a_thousand_pixels_on_its_short_side(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Photos au bon format',
                'images' => [
                    UploadedFile::fake()->image('paysage.jpg', 3000, 2000),
                    UploadedFile::fake()->image('portrait.jpg', 1200, 1600),
                    // Under the floor on both sides: it is scaled up, not left
                    // small, because the floor is what the marketplace checks.
                    UploadedFile::fake()->image('petite.jpg', 800, 600),
                ],
            ])
            ->assertRedirect();

        $listing = $product->fresh()->vintedListing;

        $expected = [
            'paysage' => [3000 / 2000, 1500, 1000],
            'portrait' => [1200 / 1600, 1000, 1333],
            'petite' => [800 / 600, 1333, 1000],
        ];

        foreach ($listing->images()->get() as $index => $image) {
            [$ratio, $width, $height] = array_values($expected)[$index];

            [$actualWidth, $actualHeight] = getimagesize(public_path('images/'.$image->image));

            $this->assertSame(1000, min($actualWidth, $actualHeight), $image->image.' is under the floor');
            $this->assertEqualsWithDelta($ratio, $actualWidth / $actualHeight, 0.002, $image->image.' was reshaped');
            $this->assertSame([$width, $height], [$actualWidth, $actualHeight], $image->image);
        }

        $this->forget($listing);
    }

    /**
     * These files are written into public/, outside the test disk: the
     * database is rebuilt between tests, the images directory is not.
     */
    private function forget(VintedListing $listing): void
    {
        foreach ($listing->images()->get() as $image) {
            @unlink(public_path('images/'.$image->image));
            @unlink(ImageThumbnailer::absoluteThumbnailPath($image->image));
        }
    }

    public function test_the_order_only_moves_this_listings_photos(): void
    {
        // An id from another listing must move nothing.
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        $mine = VintedListing::query()->create(['product_id' => $product->id, 'title' => 'A']);
        $theirs = VintedListing::query()->create(['product_id' => $other->id, 'title' => 'B']);

        $a = VintedListingImage::query()->create(['vinted_listing_id' => $mine->id, 'image' => 'vinted/a.jpg', 'sort_order' => 1]);
        $b = VintedListingImage::query()->create(['vinted_listing_id' => $mine->id, 'image' => 'vinted/b.jpg', 'sort_order' => 2]);
        $foreign = VintedListingImage::query()->create(['vinted_listing_id' => $theirs->id, 'image' => 'vinted/c.jpg', 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'A',
                'order' => [$b->id, $foreign->id, $a->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(1, $foreign->fresh()->sort_order, 'another listing\'s photo must not move');
    }

    public function test_deleting_the_product_takes_the_listing_with_it(): void
    {
        $product = Product::factory()->create();
        VintedListing::query()->create(['product_id' => $product->id, 'title' => 'Partira']);

        $product->delete();

        $this->assertSame(0, VintedListing::query()->count());
    }

    public function test_the_page_is_closed_to_anyone_who_is_not_an_admin(): void
    {
        $product = Product::factory()->create();

        $this->get(route('admin.products.vinted.edit', $product))->assertRedirect();

        // The back-office sends non-admins to the shop rather than
        // confirming the address exists.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.vinted.edit', $product))
            ->assertRedirect();
    }

    public function test_a_photo_can_be_taken_away_as_a_jpeg(): void
    {
        // The shop stores WebP, which Vinted's form will not take: the link
        // renders the same image as a JPEG without touching the file.
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Avec une photo',
                'images' => [UploadedFile::fake()->image('shot.jpg', 800, 800)],
            ]);

        $listing = $product->fresh()->vintedListing;
        $image = $listing->images()->first();
        $source = public_path('images/'.$image->image);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.photo', ['product' => $product, 'image' => $image]))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        // sku_1.jpg: the product's reference, then the photo's rank.
        $this->assertStringContainsString(Str::slug($product->sku).'_1.jpg', $response->headers->get('content-disposition'));

        // What comes out is a JPEG, and the original file is untouched.
        $this->assertSame('image/jpeg', (string) getimagesizefromstring($response->getContent())['mime']);
        $this->assertTrue(is_file($source), 'the stored photo must be left alone');

        $this->forget($listing);
    }

    public function test_every_photo_names_its_own_file(): void
    {
        // Three links carrying the same name is one file downloaded three
        // times over itself. The rank is the listing's own.
        $product = Product::factory()->create(['sku' => 'CAG-MCDES-BREATH']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.vinted.update', $product), [
                'title' => 'Trois photos',
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
            ]);

        $listing = $product->fresh()->vintedListing;
        $listing->load('images');

        $names = $listing->images->map(fn ($image) => $image->downloadName())->all();

        $this->assertSame(
            ['cag-mcdes-breath_1.jpg', 'cag-mcdes-breath_2.jpg', 'cag-mcdes-breath_3.jpg'],
            $names,
        );

        // The link carries the same name as the header: both compute it in
        // one place, and a page promising something other than the response
        // would be worse than no name at all.
        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.edit', $product))
            ->assertOk()
            ->getContent();

        foreach ($names as $name) {
            $this->assertStringContainsString('download="'.$name.'"', $html);
        }

        $this->forget($listing);
    }

    public function test_a_photo_from_another_listing_cannot_be_downloaded_through_this_product(): void
    {
        // The id is in the address: without a check it would serve any photo
        // from any product.
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        $theirs = VintedListing::query()->create(['product_id' => $other->id, 'title' => 'Ailleurs']);
        $image = VintedListingImage::query()->create([
            'vinted_listing_id' => $theirs->id,
            'image' => 'vinted/nothing.webp',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.products.vinted.photo', ['product' => $product, 'image' => $image]))
            ->assertNotFound();
    }
}
