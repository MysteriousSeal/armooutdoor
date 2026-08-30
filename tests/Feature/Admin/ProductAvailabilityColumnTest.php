<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La colonne de disponibilité dans la liste des produits.
 *
 * La liste ne montrait qu'un nombre. Zéro pouvait vouloir dire « rupture »
 * comme « à commander chez le fournisseur », et rien ne distinguait un
 * produit bien pourvu d'un autre dont il ne reste qu'une pièce. Pour un
 * produit vendu en plusieurs tailles, le total ne dit rien non plus de ce
 * qu'un client peut réellement acheter.
 */
class ProductAvailabilityColumnTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
    }

    private function variant(Product $product, int $quantity, bool $active = true, bool $atSupplier = false): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'T'.$quantity, 'fr' => 'T'.$quantity],
            'sku' => 'V-'.$product->id.'-'.$quantity.'-'.($active ? 'a' : 'i').($atSupplier ? 's' : ''),
            'quantity' => $quantity,
            'is_active' => $active,
            'supplier_id' => $atSupplier ? $this->supplier()->id : null,
            'available_at_supplier' => $atSupplier,
            'sort_order' => 1,
        ]);
    }

    public function test_a_well_stocked_product_reads_in_stock(): void
    {
        $this->assertSame('in_stock', Product::factory()->create(['quantity' => 10])->availabilityState());
    }

    public function test_two_left_reads_last_pieces(): void
    {
        $this->assertSame('low_stock', Product::factory()->create(['quantity' => 2])->availabilityState());
    }

    public function test_nothing_left_reads_out_of_stock(): void
    {
        $this->assertSame('out_of_stock', Product::factory()->create(['quantity' => 0])->availabilityState());
    }

    public function test_nothing_left_but_orderable_reads_at_supplier(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        // Zéro ne veut pas dire la même chose selon qu'on puisse le faire venir.
        $this->assertSame('at_supplier', $product->availabilityState());
    }

    public function test_a_sized_product_takes_the_best_of_its_sizes(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 8);

        // Ce qui compte est qu'un client puisse acheter quelque chose.
        $this->assertSame('in_stock', $product->fresh()->availabilityState());
    }

    public function test_a_sized_product_with_one_piece_left_reads_last_pieces(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 1);

        $this->assertSame('low_stock', $product->fresh()->availabilityState());
    }

    public function test_a_sized_product_only_orderable_reads_at_supplier(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 0, atSupplier: true);

        $this->assertSame('at_supplier', $product->fresh()->availabilityState());
    }

    public function test_an_inactive_size_does_not_count(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);
        $this->variant($product, 9, active: false);

        // Une taille retirée de la vente ne rend pas le produit disponible.
        $this->assertSame('out_of_stock', $product->fresh()->availabilityState());
    }

    public function test_a_sized_product_with_nothing_anywhere_reads_out_of_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 0);

        $this->assertSame('out_of_stock', $product->fresh()->availabilityState());
    }

    public function test_the_column_is_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['quantity' => 10]);

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('<th>Availability</th>', false)
            ->assertSee('admin-availability-chip is-in-stock', false)
            ->assertSee('In stock');
    }

    public function test_each_state_gets_its_own_class(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['quantity' => 10]);
        Product::factory()->create(['quantity' => 1]);
        Product::factory()->create(['quantity' => 0]);
        Product::factory()->create([
            'quantity' => 0, 'supplier_id' => $this->supplier()->id, 'available_at_supplier' => true,
        ]);

        $html = $this->actingAs($admin)->get('/admin/products')->assertOk()->getContent();

        foreach (['is-in-stock', 'is-low-stock', 'is-at-supplier', 'is-out-of-stock'] as $class) {
            $this->assertStringContainsString('admin-availability-chip '.$class, $html, $class.' absent');
        }
    }

    public function test_the_variant_panel_still_spans_the_whole_row(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['quantity' => 0]);
        $this->variant($product, 3);

        // La colonne ajoutée décale le panneau des déclinaisons : un colspan
        // trop court laisserait une cellule vide en bout de ligne.
        $html = $this->actingAs($admin)->get('/admin/products')->assertOk()->getContent();

        preg_match('/<thead>.*?<\/thead>/s', $html, $head);
        // « <th » seul compterait aussi la balise <thead> elle-même — c'est
        // ce qui laissait passer un colspan d'une colonne trop large.
        $columns = preg_match_all('/<th[\s>]/', $head[0]);

        $this->assertStringContainsString('colspan="'.$columns.'"', $html);
    }

    public function test_the_four_states_are_styled_in_both_themes(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        foreach (['is-in-stock', 'is-low-stock', 'is-at-supplier', 'is-out-of-stock'] as $class) {
            $this->assertMatchesRegularExpression('/\.admin-availability-chip\.'.$class.'\s*\{/', $css);
            $this->assertMatchesRegularExpression("/\[data-theme='dark'\]\s*\.admin-availability-chip\.".$class.'\s*\{/', $css);
        }
    }
}
