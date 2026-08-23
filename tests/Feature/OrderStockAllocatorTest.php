<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\OrderStockAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prendre le stock d'une ligne, et dire ce qui s'est passé.
 *
 * Les mêmes vingt lignes vivaient à trois endroits. Les deux tunnels client
 * en étaient des copies au caractère près ; celle de l'administration avait
 * dérivé dans l'autre sens et refuse une ligne qu'elle ne peut pas couvrir,
 * là où un client serait mis en réassort. Les deux comportements sont
 * voulus : la différence est devenue un paramètre.
 */
class OrderStockAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private function allocator(): OrderStockAllocator
    {
        return app(OrderStockAllocator::class);
    }

    private function product(int $quantity, bool $backorderable = false, ?int $leadTime = null): Product
    {
        $supplier = $backorderable
            ? Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => $leadTime])
            : null;

        return Product::factory()->create([
            'quantity' => $quantity,
            'supplier_id' => $supplier?->id,
            'available_at_supplier' => $backorderable,
        ]);
    }

    public function test_a_covered_line_is_served_from_the_shelf(): void
    {
        $product = $this->product(10);

        $allocation = $this->allocator()->allocate($product, null, 3, allowBackorder: true);

        $this->assertFalse($allocation->backordered);
        $this->assertNull($allocation->supplierLeadTimeDays);
        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_a_short_line_goes_on_backorder_when_allowed(): void
    {
        $product = $this->product(2, backorderable: true, leadTime: 5);

        $allocation = $this->allocator()->allocate($product, null, 6, allowBackorder: true);

        $this->assertTrue($allocation->backordered);
        $this->assertSame(5, $allocation->supplierLeadTimeDays);
    }

    public function test_a_backorder_empties_the_shelf_without_going_negative(): void
    {
        $product = $this->product(2, backorderable: true);

        $this->allocator()->allocate($product, null, 6, allowBackorder: true);

        // Ce qui manque est porté par le drapeau de réassort, pas par un
        // stock négatif.
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_a_short_line_is_refused_when_backordering_is_off(): void
    {
        $product = $this->product(2, backorderable: true, leadTime: 5);

        // Le cas de l'administration : même un produit réassortissable est
        // refusé, pour ne pas mettre l'acheteur en attente à son insu.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stock');

        $this->allocator()->allocate($product, null, 6, allowBackorder: false);
    }

    public function test_nothing_is_taken_when_the_line_is_refused(): void
    {
        $product = $this->product(2, backorderable: true);

        try {
            $this->allocator()->allocate($product, null, 6, allowBackorder: false);
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertSame(2, $product->fresh()->quantity);
    }

    public function test_a_product_that_cannot_be_backordered_is_refused_even_for_a_customer(): void
    {
        $product = $this->product(2);

        $this->expectException(\RuntimeException::class);

        $this->allocator()->allocate($product, null, 6, allowBackorder: true);
    }

    public function test_a_variant_is_taken_from_its_own_stock(): void
    {
        $product = $this->product(0);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 8,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $allocation = $this->allocator()->allocate($product, $variant, 3, allowBackorder: true);

        $this->assertFalse($allocation->backordered);
        $this->assertSame(5, $variant->fresh()->quantity);
    }

    public function test_the_product_total_is_recomputed_after_a_variant_moves(): void
    {
        $product = $this->product(0);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 8,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->allocator()->allocate($product, $variant, 3, allowBackorder: true);

        // Le stock du produit est un total : sans recalcul il resterait sur
        // l'ancien chiffre et la fiche mentirait.
        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_only_the_three_call_sites_touch_stock(): void
    {
        // La duplication reviendrait par un quatrième endroit qui décrémente
        // dans son coin.
        $sources = [
            app_path('Http/Controllers/CheckoutController.php'),
            app_path('Services/StripeCheckoutFinalizer.php'),
            app_path('Http/Controllers/Admin/OrderController.php'),
        ];

        foreach ($sources as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString(
                'isBackorderable()',
                $source,
                basename($path).' décide encore du réassort dans son coin'
            );
        }
    }

    public function test_the_question_and_the_answer_agree(): void
    {
        // canAllocate() sert avant que la commande existe, allocate() quand
        // il faut prendre. Les deux doivent dire la même chose, sinon on
        // retombe sur deux règles concurrentes.
        $cases = [
            [$this->product(10), 3, true],
            [$this->product(2), 6, false],
            [$this->product(2, backorderable: true), 6, true],
        ];

        foreach ([true, false] as $allowBackorder) {
            foreach ($cases as [$product, $quantity, $coveredWithBackorder]) {
                $product = $product->fresh();
                $expected = $allowBackorder ? $coveredWithBackorder : $product->quantity >= $quantity;

                $this->assertSame(
                    $expected,
                    $this->allocator()->canAllocate($product, null, $quantity, $allowBackorder),
                );

                $threw = false;

                try {
                    $this->allocator()->allocate($product, null, $quantity, $allowBackorder);
                } catch (\RuntimeException) {
                    $threw = true;
                }

                $this->assertSame($expected, ! $threw, 'la question et la prise divergent');
            }
        }
    }

    public function test_asking_takes_nothing(): void
    {
        $product = $this->product(10);

        $this->allocator()->canAllocate($product, null, 3, allowBackorder: true);

        $this->assertSame(10, $product->fresh()->quantity);
    }
}
