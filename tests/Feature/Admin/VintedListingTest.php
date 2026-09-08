<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Models\VintedListing;
use App\Models\VintedListingImage;
use App\Support\ImageThumbnailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * L'annonce Vinted d'un produit.
 *
 * Rien n'est envoyé à Vinted — la page compose et garde le texte. Ce qu'elle
 * doit tenir : un brouillon parti de la fiche, une seule annonce par produit,
 * et des photos qui n'appartiennent qu'à elle.
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
        // Une annonce se rattache à une fiche enregistrée : le formulaire de
        // création n'a pas encore de produit à quoi l'accrocher.
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
            // Le produit se nomme en tête de page, pour savoir de quoi on
            // parle — mais les champs, eux, sont vides.
            ->assertSee('Cagoule camouflage')
            ->getContent();

        // Les deux champs de texte arrivent vides. Le titre se lit sur son
        // attribut plutôt qu'à l'absence du nom, qui figure en tête de page.
        $this->assertMatchesRegularExpression('#id="vinted-title".*?value=""#s', $html);
        $this->assertMatchesRegularExpression('#id="vinted-description".*?>\s*</textarea>#s', $html);
        $this->assertStringNotContainsString('Respirante et légère', $html);

        // Le prix non plus n'est pas repris : celui de la boutique se lit à
        // côté du champ, il ne s'y installe pas.
        $this->assertMatchesRegularExpression('#id="vinted-price".*?value=""#s', $html);
        $this->assertStringContainsString('Shop price', $html);

        // Ouvrir la page n'écrit rien : tant qu'on n'enregistre pas, il n'y a
        // pas d'annonce.
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
        // Une seule annonce par produit : deux brouillons obligeraient chaque
        // écran à choisir lequel montrer.
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
        // La locale de l'application est le français, celle de la boutique.
        // Le back-office est en anglais de bout en bout, et un « il y a une
        // minute » au milieu d'une page anglaise vient de là.
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
        // On écrit le texte un jour, on fixe le prix un autre.
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
        // Les fichiers vivent à part de ceux du catalogue.
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
        // La vignette part avec elle : plus aucune ligne ne la nomme, donc
        // rien ne viendrait la balayer plus tard.
        $this->assertFalse(is_file($droppedThumb), 'the removed photo should take its thumbnail with it');

        $this->forget($listing);
    }

    /**
     * Ces fichiers-là sont écrits dans public/, hors du disque de test : la
     * base est reconstruite entre deux tests, pas le dossier d'images.
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
        // Un identifiant venu d'une autre annonce ne doit rien déplacer.
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

        // Le back-office renvoie les non-admins vers la boutique plutôt que
        // de leur confirmer que l'adresse existe.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.vinted.edit', $product))
            ->assertRedirect();
    }

    public function test_a_photo_can_be_taken_away_as_a_jpeg(): void
    {
        // La boutique stocke du WebP, dont le formulaire de Vinted ne veut
        // pas : le lien rend la même image en JPEG sans toucher au fichier.
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
        // sku_1.jpg : la référence du produit, puis le rang de la photo.
        $this->assertStringContainsString(\Illuminate\Support\Str::slug($product->sku).'_1.jpg', $response->headers->get('content-disposition'));

        // Ce qui sort est bien un JPEG, et le fichier d'origine est intact.
        $this->assertSame('image/jpeg', (string) getimagesizefromstring($response->getContent())['mime']);
        $this->assertTrue(is_file($source), 'the stored photo must be left alone');

        $this->forget($listing);
    }

    public function test_every_photo_names_its_own_file(): void
    {
        // Trois liens qui portent le même nom, c'est un fichier téléchargé
        // trois fois par-dessus lui-même. Le rang est celui de l'annonce.
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

        // Le lien porte le même nom que l'en-tête : les deux le calculent au
        // même endroit, et une page qui promettrait autre chose que la
        // réponse serait pire que pas de nom du tout.
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
        // L'identifiant est dans l'adresse : sans contrôle, il servirait
        // n'importe quelle photo depuis n'importe quel produit.
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
