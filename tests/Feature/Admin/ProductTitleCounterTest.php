<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTitleCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_name_field_carries_a_soft_sixty_character_counter(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('data-char-counter="name-counter"', false)
            ->assertSee('data-char-limit="60"', false)
            ->assertSee('data-char-min="20"', false)
            ->assertSee('id="name-counter"', false)
            // A warning, not a wall: the hard maxlength stays the column's 120.
            ->assertSee('maxlength="120"', false);
    }
}
