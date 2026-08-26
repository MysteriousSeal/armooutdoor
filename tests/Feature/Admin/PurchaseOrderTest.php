<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le cycle de vie d'un bon de commande fournisseur.
 *
 * Brouillon modifiable, envoi qui fige les lignes, annulation et suppression
 * réservées au propriétaire. Le stock ne bouge jamais ici : il ne monte qu'à
 * la réception, testée séparément.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'DM Diffusion', 'lead_time_days' => 4]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Supplier $supplier, Product $product, array $overrides = []): array
    {
        return [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'cost' => '3.50'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_created_with_a_number_and_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_id' => $supplier->id, 'supplier_price_cents' => 350]);

        $this->actingAs($admin)
            ->post('/admin/purchase-orders', $this->payload($supplier, $product))
            ->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^BC-\d{8}-[A-Z0-9]{4}$/', $po->number);
        $this->assertSame('draft', $po->status);
        $this->assertSame('DM Diffusion', $po->supplier_name);
        $this->assertSame($admin->id, $po->created_by_user_id);
        $this->assertSame(350, $po->items->first()->unit_cost_cents);
        $this->assertSame($product->localizedName(), $po->items->first()->name);
    }

    public function test_the_cost_prefills_from_the_supplier_price_when_left_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_price_cents' => 1234]);

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'cost' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1234, PurchaseOrder::query()->firstOrFail()->items->first()->unit_cost_cents);
    }

    public function test_an_order_without_lines_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/purchase-orders', ['supplier_id' => $this->supplier()->id, 'items' => []])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_creating_moves_no_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['quantity' => 3]);

        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));

        // Commander n'est pas recevoir.
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_a_draft_can_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 9, 'cost' => '2.00']],
        ])->assertSessionHasNoErrors();

        $item = $po->fresh()->items->first();
        $this->assertSame(9, $item->quantity_ordered);
        $this->assertSame(200, $item->unit_cost_cents);
    }

    public function test_sending_locks_the_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po))->assertRedirect();

        $po->refresh();
        $this->assertSame('sent', $po->status);
        $this->assertNotNull($po->sent_at);

        // Plus modifiable : le bon est parti chez le fournisseur.
        $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $po))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), $this->payload($supplier, $product))->assertNotFound();
    }

    public function test_staff_can_create_and_send_but_not_cancel_or_delete(): void
    {
        $staff = User::factory()->admin()->create(['role' => 'staff']);
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($staff)->post('/admin/purchase-orders', $this->payload($supplier, $product))->assertSessionHasNoErrors();
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($staff)->delete(route('admin.purchase-orders.destroy', $po))->assertForbidden();

        $this->actingAs($staff)->patch(route('admin.purchase-orders.send', $po))->assertRedirect();
        $this->actingAs($staff)->patch(route('admin.purchase-orders.cancel', $po))->assertForbidden();

        $this->assertSame('sent', $po->fresh()->status);
    }

    public function test_the_owner_can_cancel_a_sent_order(): void
    {
        $owner = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($owner)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.purchase-orders.send', $po));

        $this->actingAs($owner)->patch(route('admin.purchase-orders.cancel', $po))->assertRedirect();

        $po->refresh();
        $this->assertSame('cancelled', $po->status);
        $this->assertNotNull($po->cancelled_at);
    }

    public function test_only_a_draft_can_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($owner)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.purchase-orders.send', $po));

        // Un bon envoyé est la trace de quelque chose qui a eu lieu.
        $this->actingAs($owner)->delete(route('admin.purchase-orders.destroy', $po))->assertForbidden();
        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_a_deleted_supplier_leaves_the_order_readable(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));

        $supplier->delete();

        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertNull($po->supplier_id);
        $this->assertSame('DM Diffusion', $po->supplier_name);
        $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->assertSee('DM Diffusion');
    }

    public function test_the_lifecycle_is_written_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po));
        $this->actingAs($admin)->patch(route('admin.purchase-orders.cancel', $po));

        foreach (['purchase_order.created', 'purchase_order.sent', 'purchase_order.cancelled'] as $action) {
            $this->assertTrue(AdminActivityLog::query()->where('action', $action)->exists(), $action);
        }
    }

    public function test_the_index_lists_and_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/purchase-orders?tab=draft')
            ->assertOk()
            ->assertSee($po->number)
            ->assertSee('DM Diffusion');
    }

    public function test_the_nav_badge_counts_orders_awaiting_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        // Un brouillon n'attend rien : pas de pastille.
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertDontSee('awaiting receipt');

        $this->actingAs($admin)->patch(route('admin.purchase-orders.send', $po));

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('1 awaiting receipt');
    }

    public function test_a_price_typed_incl_vat_is_stored_excl_vat(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        // 4,20 € TTC à 20 % : la ligne garde 3,50 € HT. Le taux n'est qu'une
        // aide de saisie, rien de la TVA n'est conservé sur le bon.
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '12.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '4.20']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertSame(350, $po->items->first()->unit_cost_cents);
        $this->assertSame(1000, $po->shipping_cents);
    }

    public function test_each_french_rate_converts_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();

        foreach (['0' => 1000, '5.5' => 948, '10' => 909, '20' => 833] as $rate => $expected) {
            $product = Product::factory()->create();

            $this->actingAs($admin)->post('/admin/purchase-orders', [
                'supplier_id' => $supplier->id,
                'vat_rate' => (string) $rate,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
            ])->assertSessionHasNoErrors();

            $this->assertSame(
                $expected,
                PurchaseOrder::query()->latest('id')->firstOrFail()->items->first()->unit_cost_cents,
                'taux '.$rate,
            );
        }
    }

    public function test_a_blank_cost_still_prefills_from_the_supplier_price_unconverted(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['supplier_price_cents' => 1234]);

        // Le prix d'achat de la fiche est déjà HT : le taux ne doit pas le
        // diviser une seconde fois.
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1234, PurchaseOrder::query()->firstOrFail()->items->first()->unit_cost_cents);
    }

    public function test_an_unknown_vat_rate_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '19.6',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
        ])->assertSessionHasErrors('vat_rate');
    }

    public function test_the_form_offers_the_rate_selector(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/purchase-orders/create')
            ->assertOk()
            ->assertSee('name="vat_rate"', false)
            ->assertSee('Incl. VAT at 20%')
            ->assertSee('always stored excl. VAT');
    }

    public function test_the_lines_render_as_a_grid_with_one_set_of_headings(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin/purchase-orders/create')->assertOk()->getContent();

        // Les libellés une seule fois en tête, pas répétés sur chaque ligne.
        $this->assertSame(1, substr_count($html, 'po-lines-head"'));
        $this->assertStringContainsString('po-line-total', $html);
        $this->assertStringContainsString('data-total-grand', $html);
        $this->assertStringContainsString('po-item-row-template', $html);
    }

    public function test_an_edit_form_keeps_the_same_field_names(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        // Le relookage ne doit pas avoir changé le contrat du formulaire.
        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $po))
            ->assertOk()
            ->assertSee('items[0][product_id]', false)
            ->assertSee('items[0][quantity]', false)
            ->assertSee('items[0][cost]', false)
            ->assertSee('name="vat_rate"', false);
    }

    public function test_the_chosen_rate_is_kept_on_the_order(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '2.40']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();

        // Le taux n'est plus jeté : la commande doit pouvoir redire ce qui a
        // été payé.
        $this->assertSame(2000, $po->vat_rate_basis_points);
        $this->assertSame(20.0, $po->vatRatePercent());
        $this->assertTrue($po->hasVat());
        $this->assertSame(200, $po->items->first()->unit_cost_cents);
    }

    public function test_an_order_entered_excl_vat_carries_no_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '0',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '2.00']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();

        $this->assertSame(0, $po->vat_rate_basis_points);
        $this->assertFalse($po->hasVat());
        $this->assertSame($po->totalCents(), $po->totalInclVatCents());
    }

    public function test_the_incl_vat_figures_reproduce_the_supplier_invoice(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        // Six unités à 2,00 € TTC : la facture dit 12,00 €. Passer par le
        // total HT arrondi (10,02 €) donnerait 12,02 € — deux centimes à côté.
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 6, 'cost' => '2.00']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();
        $item = $po->items->first();

        $this->assertSame(167, $item->unit_cost_cents);
        $this->assertSame(1002, $item->lineTotalCents());
        $this->assertSame(200, $po->withVatCents($item->unit_cost_cents));
        $this->assertSame(1200, $po->lineTotalInclVatCents($item));
        $this->assertSame(1200, $po->totalInclVatCents());
    }

    public function test_shipping_is_included_in_the_incl_vat_total(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '6.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '12.00']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();

        $this->assertSame(1000, $po->items->first()->unit_cost_cents);
        $this->assertSame(500, $po->shipping_cents);
        $this->assertSame(1500, $po->totalCents());
        $this->assertSame(1800, $po->totalInclVatCents());
        $this->assertSame(300, $po->vatAmountCents());
    }

    public function test_the_page_shows_the_rate_and_both_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'cost' => '2.40']],
        ]);
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->assertSee('supplier prices included 20% VAT')
            ->assertSee('Total excl. VAT')
            ->assertSee('Total incl. VAT')
            ->assertSee('VAT 20%');
    }

    public function test_an_order_without_vat_hides_the_extra_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        // Rien à montrer : les colonnes TTC ne doivent pas encombrer.
        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->assertDontSee('Total incl. VAT')
            ->assertDontSee('incl. VAT');
    }

    public function test_a_half_point_rate_reads_back_without_a_trailing_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '5.5',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.55']],
        ]);
        $po = PurchaseOrder::query()->firstOrFail();

        $this->assertSame(550, $po->vat_rate_basis_points);
        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->assertSee('VAT 5.5%');
    }

    public function test_editing_a_draft_keeps_the_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '2.40']],
        ]);
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), [
            'supplier_id' => $supplier->id,
            'vat_rate' => '10',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '2.20']],
        ])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame(1000, $po->vat_rate_basis_points);
        $this->assertSame(200, $po->items->first()->unit_cost_cents);
    }

    public function test_a_line_shows_its_thumbnail_first_and_its_sku_under_the_name(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['sku' => 'TAPE-CAMO-DSRT-50X45', 'image' => 'products/tape.webp']);
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->getContent();

        // La vignette ouvre la ligne, le nom suit, la référence se glisse
        // dessous — plus de colonne SKU à elle seule.
        $this->assertStringContainsString('admin-product-thumb', $html);
        $this->assertStringContainsString($product->thumbnailUrl(), $html);
        $this->assertStringContainsString('<span class="admin-table-sub">TAPE-CAMO-DSRT-50X45</span>', $html);
        $this->assertStringNotContainsString('<th>SKU</th>', $html);

        $this->assertLessThan(
            strpos($html, 'admin-table-strong'),
            strpos($html, 'admin-product-thumb'),
            'la vignette doit précéder le nom',
        );
    }

    public function test_a_line_whose_product_is_gone_keeps_an_empty_tile(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();
        $product->delete();

        // Sans tuile, le nom glisserait à gauche et la colonne se désalignerait.
        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->assertSee('admin-stock-media is-empty', false)
            ->assertSee('product deleted');
    }

    public function test_the_thumbnail_column_has_a_width(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        // Sans largeur, le nom du produit ne commencerait pas au même endroit
        // d'une ligne à l'autre.
        $this->assertMatchesRegularExpression('/\.admin-table-media\s*\{/', $css);
    }

    public function test_a_long_name_is_clamped_rather_than_squeezing_the_tile(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $long = 'Ruban Adhésif 5cm x 4.5m Sport Camouflage Noir Urbain Auto-Agrippant Extensible Outdoor Chasse';
        $product = Product::factory()->create(['name' => ['fr' => $long], 'image' => 'products/tape.webp']);
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->getContent();

        // Coupé à l'affichage, pas à l'enregistrement : le nom complet reste
        // dans la ligne et au survol.
        $this->assertStringContainsString('admin-name-clamp', $html);
        $this->assertStringContainsString('title="'.e($long).'"', $html);
        $this->assertStringContainsString($long, $html);
    }

    public function test_the_clamp_and_the_tile_floor_are_declared(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        // Sans le plancher, le max-width global des images laisse la tuile
        // s'écraser dans une cellule serrée.
        $this->assertMatchesRegularExpression('/\.admin-name-clamp\s*\{[^}]*line-clamp:\s*2/s', $css);
        // Le sélecteur est groupé avec .admin-stock-media : il peut donc être
        // suivi d'une virgule autant que d'une accolade.
        $this->assertMatchesRegularExpression('/\.admin-table-media\s+\.admin-product-thumb\s*[,{][^}]*min-width/s', $css);
        $this->assertMatchesRegularExpression('/\.admin-table-media\s+\.admin-product-thumb\s*[,{][^}]*min-height/s', $css);
    }

    public function test_a_variant_with_its_own_image_shows_that_image(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['quantity' => 0, 'image' => 'products/parent.webp']);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'image' => 'products/variant.webp',
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'cost' => '1.00']],
        ])->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', PurchaseOrder::query()->firstOrFail()))
            ->assertOk()
            ->getContent();

        // C'est la déclinaison qui a été commandée : c'est elle qu'on montre.
        $this->assertStringContainsString($variant->thumbnailUrl(), $html);
        $this->assertStringNotContainsString($product->thumbnailUrl(), $html);
    }

    public function test_a_variant_without_an_image_falls_back_to_the_product(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create(['quantity' => 0, 'image' => 'products/parent.webp']);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'image' => null,
            'quantity' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'cost' => '1.00']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', PurchaseOrder::query()->firstOrFail()))
            ->assertOk()
            ->assertSee($product->thumbnailUrl(), false);
    }

    public function test_the_thumbnail_cell_carries_the_class_that_keeps_it_square(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create([
            'name' => ['fr' => 'Ruban Adhésif 5cm x 4.5m Sport Camouflage Noir Urbain Auto-Agrippant Extensible Outdoor Chasse'],
            'image' => 'products/tape.webp',
        ]);
        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->getContent();

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $thumbnails = (new \DOMXPath($document))->query('//table//tbody//img[contains(@class, "admin-product-thumb")]');

        $this->assertGreaterThan(0, $thumbnails->length);

        // Le plancher de taille est écrit pour .admin-table-media img. Sans
        // la classe sur la cellule, la règle ne vise rien : mesurée dans un
        // navigateur, la vignette tombait à 20x44 au lieu de 44x44.
        foreach ($thumbnails as $thumbnail) {
            $cell = $thumbnail->parentNode->parentNode;

            $this->assertStringContainsString(
                'admin-table-media',
                $cell->getAttribute('class'),
                'la cellule de vignette doit porter admin-table-media',
            );
        }
    }

    public function test_reopening_a_draft_remembers_the_vat_rate_and_shows_the_prices_as_typed(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '6.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'cost' => '4.27']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();
        $this->assertSame(356, $po->items->first()->unit_cost_cents);

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $po))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="20" selected>', $html);

        // Le taux dit « ce que je tape est TTC » : les montants doivent donc
        // se relire en TTC, pas en HT.
        $this->assertStringContainsString('value="4.27"', $html);
        $this->assertStringContainsString('value="6.00"', $html);
    }

    public function test_saving_a_draft_again_without_touching_it_changes_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '6.00',
            'items' => [['product_id' => $product->id, 'quantity' => 18, 'cost' => '0.64']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();

        // Ce que le formulaire de modification réaffiche, renvoyé tel quel.
        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '6.00',
            'items' => [['product_id' => $product->id, 'quantity' => 18, 'cost' => '0.64']],
        ])->assertSessionHasNoErrors();

        $po->refresh()->load('items');

        $this->assertSame(2000, $po->vat_rate_basis_points);
        $this->assertSame(53, $po->items->first()->unit_cost_cents);
        $this->assertSame(500, $po->shipping_cents);
    }

    public function test_discount_and_additional_costs_follow_the_vat_mode_like_shipping(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'vat_rate' => '20',
            'shipping_price' => '12.00',
            'discount_price' => '6.00',
            'additional_costs_price' => '24.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->firstOrFail();

        // Saisis TTC à 20 %, ramenés au HT comme le port :
        // 12,00 / 1,2 = 10,00 ; 24,00 / 1,2 = 20,00 ; 6,00 / 1,2 = 5,00.
        $this->assertSame(1000, $po->shipping_cents);
        $this->assertSame(2000, $po->additional_costs_cents);
        $this->assertSame(500, $po->discount_cents);
    }

    public function test_additional_costs_are_added_and_discount_is_subtracted_from_the_total(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '100.00']],
            'shipping_price' => '10.00',
            'additional_costs_price' => '5.00',
            'discount_price' => '3.00',
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();

        // 100 + 10 + 5 - 3 = 112, en HT comme les autres postes ici (pas de
        // taux saisi).
        $this->assertSame(11200, $po->totalCents());
        $this->assertSame(11200, $po->totalInclVatCents());
    }

    public function test_a_purchase_order_without_either_field_behaves_as_before(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));

        $po = PurchaseOrder::query()->firstOrFail();

        $this->assertSame(0, $po->discount_cents);
        $this->assertSame(0, $po->additional_costs_cents);
    }

    public function test_editing_a_draft_keeps_discount_and_additional_costs(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
            'discount_price' => '2.00',
            'additional_costs_price' => '4.00',
        ]);
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.purchase-orders.update', $po), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
            'discount_price' => '2.00',
            'additional_costs_price' => '4.00',
        ])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame(200, $po->discount_cents);
        $this->assertSame(400, $po->additional_costs_cents);
    }

    public function test_the_show_page_lists_discount_and_additional_costs_when_set(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'cost' => '10.00']],
            'discount_price' => '2.00',
            'additional_costs_price' => '4.00',
        ]);
        $po = PurchaseOrder::query()->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->getContent();

        $this->assertStringContainsString('Additional costs', $html);
        $this->assertStringContainsString('4,00', $html);
        $this->assertStringContainsString('>Discount<', $html);
        $this->assertStringContainsString('−2,00', $html);
    }

    public function test_the_show_page_stays_quiet_about_them_when_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/admin/purchase-orders', $this->payload($supplier, $product));
        $po = PurchaseOrder::query()->firstOrFail();

        $html = $this->actingAs($admin)->get(route('admin.purchase-orders.show', $po))->assertOk()->getContent();

        $this->assertStringNotContainsString('Additional costs', $html);
        $this->assertStringNotContainsString('>Discount<', $html);
    }

    public function test_charges_are_shared_across_lines_by_quantity_received(): void
    {
        $supplier = $this->supplier();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'additional_costs_cents' => 1200,
            'discount_cents' => 0,
        ]);

        // 5 unités contre 15 : la ligne de 15 doit toucher trois fois plus.
        $small = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'A',
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost_cents' => 100,
        ]);
        $large = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'B',
            'quantity_ordered' => 15,
            'quantity_received' => 15,
            'unit_cost_cents' => 100,
        ]);
        $po->load('items');

        $this->assertSame(20, $po->totalReceivedQuantity());
        $this->assertSame(300, $po->lineShareOfChargesCents($small));
        $this->assertSame(900, $po->lineShareOfChargesCents($large));
    }

    public function test_a_discount_reduces_the_line_share_and_can_go_negative(): void
    {
        $supplier = $this->supplier();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'additional_costs_cents' => 0,
            'discount_cents' => 500,
        ]);
        $item = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'A',
            'quantity_ordered' => 10,
            'quantity_received' => 10,
            'unit_cost_cents' => 100,
        ]);
        $po->load('items');

        $this->assertSame(-500, $po->lineShareOfChargesCents($item));
    }

    public function test_an_unreceived_line_gets_no_share_of_anything(): void
    {
        $supplier = $this->supplier();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'additional_costs_cents' => 1000,
        ]);
        $item = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => Product::factory()->create()->id,
            'name' => 'A',
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_cents' => 100,
        ]);
        $po->load('items');

        $this->assertSame(0, $po->totalReceivedQuantity());
        $this->assertSame(0, $po->lineShareOfChargesCents($item));
    }
}
