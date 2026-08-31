<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a product looks like in a search result.
 *
 * Names here describe the article in full and run past a hundred characters
 * doing it, while a result truncates around sixty; the description was cut at
 * exactly 160 characters, which landed mid-word about as often as not.
 */
class ProductSerpTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_title_carries_the_shop_however_long_the_name(): void
    {
        // The suffix used to come off once the title passed sixty characters,
        // which made the shop's name appear and disappear between one product
        // and the next for no reason a reader could see. Every other page on
        // the site appends it unconditionally; products do too.
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => ['fr' => 'Lot 100 Cibles Autocollantes Réactives Rondes 76 mm Rouge et Noir 5 Zones avec Pastilles'],
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>Lot 100 Cibles Autocollantes Réactives Rondes 76 mm Rouge et Noir 5 Zones avec Pastilles — Armo Outdoor</title>', false);
    }

    public function test_a_short_name_still_carries_the_shop(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => ['fr' => 'Sangle Tactique 1 Point QD'],
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>Sangle Tactique 1 Point QD — Armo Outdoor</title>', false);
    }

    public function test_a_meta_title_overrides_the_name(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => ['fr' => 'Lot 100 Cibles Autocollantes Réactives Rondes 76 mm Rouge et Noir 5 Zones avec Pastilles'],
            'meta_title' => 'Cibles réactives 76 mm, lot de 100',
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>Cibles réactives 76 mm, lot de 100 — Armo Outdoor</title>', false);
    }

    public function test_a_meta_description_overrides_the_derived_one(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'description' => ['fr' => 'Une longue description dont les premières phrases ne sont pas celles qui vendent.'],
            'meta_description' => 'Cibles réactives 76 mm : l\'impact se voit du pas de tir, sans lunette.',
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('content="Cibles réactives 76 mm : l&#039;impact se voit du pas de tir, sans lunette."', false);
    }

    public function test_a_product_without_one_still_derives_its_description(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'description' => ['fr' => 'Une sangle, un point. '.str_repeat('Du texte qui continue longtemps. ', 12)],
            'meta_description' => null,
        ]);

        $this->assertStringEndsWith('.', $product->metaDescription());
        $this->assertLessThanOrEqual(160, mb_strlen($product->metaDescription()));
    }

    public function test_a_description_stops_at_the_end_of_a_sentence(): void
    {
        $this->assertSame(
            'Lot de 200 cibles autocollantes rondes de 76 mm, réactives et fluorescentes. Chaque impact éclate en une gerbe jaune vif.',
            meta_description('Lot de 200 cibles autocollantes rondes de 76 mm, réactives et fluorescentes. Chaque impact éclate en une gerbe jaune vif. Le principe est simple : la cible est imprimée en couches.'),
        );
    }

    public function test_a_description_with_no_sentence_break_stops_at_a_word(): void
    {
        $cut = meta_description(str_repeat('cible ', 60));

        $this->assertLessThanOrEqual(160, mb_strlen($cut));
        $this->assertStringEndsWith('cible…', $cut);
        $this->assertStringNotContainsString('cibl…', $cut);
    }

    public function test_a_description_short_enough_is_left_alone(): void
    {
        $this->assertSame('Une sangle, un point.', meta_description('Une sangle, un point.'));
    }
}
