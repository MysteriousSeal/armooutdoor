<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivePurchaseOrderRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderRequest;
use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\PurchaseOrderReceiver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderReceiver $receiver) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['open', 'draft', 'received', 'cancelled'], true)
            ? $request->query('tab')
            : 'open';
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id') ? (int) $request->query('supplier_id') : null;

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->tap(fn ($query) => $this->applyTab($query, $tab))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhere('supplier_name', 'like', '%'.$search.'%')
                        ->orWhere('reference', 'like', '%'.$search.'%');
                });
            })
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        $open = PurchaseOrder::query()->open()->with('items')->get();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'tab' => $tab,
            'search' => $search,
            'supplierId' => $supplierId,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'openCount' => $open->count(),
            'draftCount' => PurchaseOrder::query()->where('status', 'draft')->count(),
            'receivedCount' => PurchaseOrder::query()->where('status', 'received')->count(),
            'cancelledCount' => PurchaseOrder::query()->where('status', 'cancelled')->count(),
            'unitsOnOrder' => (int) $open->sum(
                fn (PurchaseOrder $po): int => $po->items->sum(
                    fn (PurchaseOrderItem $item): int => $item->quantityRemaining(),
                ),
            ),
            'committedCostCents' => (int) $open->sum(
                fn (PurchaseOrder $po): int => $po->totalCents() - $po->receivedValueCents(),
            ),
        ]);
    }

    public function create(): View
    {
        return view('admin.purchase-orders.create', [
            'purchaseOrder' => null,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'products' => $this->productsForForm(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $purchaseOrder = DB::transaction(function () use ($request): PurchaseOrder {
            $supplier = Supplier::query()->findOrFail($request->input('supplier_id'));

            $purchaseOrder = PurchaseOrder::createWithNumber([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'status' => 'draft',
                'reference' => $request->input('reference'),
                'expected_at' => $request->input('expected_at'),
                'notes' => $request->input('notes'),
                'shipping_cents' => $request->shippingCents(),
                'vat_rate_basis_points' => $request->vatRateBasisPoints(),
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->writeItems($purchaseOrder, $request);
            $purchaseOrder->markStatus('draft');

            return $purchaseOrder;
        });

        AdminActivityLog::record('purchase_order.created', $purchaseOrder, 'Created purchase order '.$purchaseOrder->number);

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Purchase order drafted.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items.product', 'items.variant', 'statusHistories.user', 'createdBy']);

        return view('admin.purchase-orders.show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->isEditable(), 404);

        $purchaseOrder->load('items');

        return view('admin.purchase-orders.create', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'products' => $this->productsForForm(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isEditable(), 404);

        DB::transaction(function () use ($request, $purchaseOrder): void {
            $supplier = Supplier::query()->findOrFail($request->input('supplier_id'));

            $purchaseOrder->update([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'reference' => $request->input('reference'),
                'expected_at' => $request->input('expected_at'),
                'notes' => $request->input('notes'),
                'shipping_cents' => $request->shippingCents(),
                'vat_rate_basis_points' => $request->vatRateBasisPoints(),
            ]);

            $purchaseOrder->items()->delete();
            $this->writeItems($purchaseOrder, $request);
        });

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Draft saved.');
    }

    public function send(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 403);

        $purchaseOrder->update(['sent_at' => now()]);
        $purchaseOrder->markStatus('sent');
        AdminActivityLog::record('purchase_order.sent', $purchaseOrder, 'Sent purchase order '.$purchaseOrder->number.' to '.$purchaseOrder->supplier_name);

        return back()->with('status', 'Purchase order marked as sent — its lines are now locked.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->receiver->receive($purchaseOrder, $request->validated('lines', []));

        AdminActivityLog::record('purchase_order.received', $purchaseOrder, 'Received stock on purchase order '.$purchaseOrder->number);

        return back()->with('status', 'Stock received.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->canBeCancelled(), 403);

        $purchaseOrder->update(['cancelled_at' => now()]);
        // Stock already received stays: those goods physically arrived.
        // Cancelling only closes out what was still expected.
        $purchaseOrder->markStatus('cancelled');
        AdminActivityLog::record('purchase_order.cancelled', $purchaseOrder, 'Cancelled purchase order '.$purchaseOrder->number);

        return back()->with('status', 'Purchase order cancelled. Stock already received is untouched.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->canBeDeleted(), 403);

        AdminActivityLog::record('purchase_order.deleted', $purchaseOrder, 'Deleted draft purchase order '.$purchaseOrder->number);
        $purchaseOrder->delete();

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('status', 'Draft deleted.');
    }

    private function applyTab($query, string $tab): void
    {
        match ($tab) {
            'draft' => $query->where('status', 'draft'),
            'received' => $query->where('status', 'received'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query->open(),
        };
    }

    private function productsForForm()
    {
        return Product::query()->with(['variants', 'supplier'])->orderBy('name')->get();
    }

    private function writeItems(PurchaseOrder $purchaseOrder, StorePurchaseOrderRequest $request): void
    {
        foreach ($request->filledItems() as $row) {
            $product = Product::query()->with('variants')->findOrFail($row['product_id']);
            $variant = $row['variant_id'] !== null
                ? $product->variants->firstWhere('id', $row['variant_id'])
                : null;

            $unitCostCents = filled($row['cost'])
                ? $request->toExVatCents((string) $row['cost'])
                // Prix d'achat de la fiche produit : déjà HT, il ne passe pas
                // par la conversion. Les déclinaisons n'ont pas de prix
                // d'achat propre.
                : (int) ($product->supplier_price_cents ?? 0);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'name' => $product->localizedName().($variant && $variant->label() !== '' ? ' — '.$variant->label() : ''),
                'sku' => $variant?->sku ?? $product->sku,
                'supplier_reference' => $variant?->supplier_reference ?? $product->supplier_reference,
                'quantity_ordered' => $row['quantity'],
                'unit_cost_cents' => $unitCostCents,
            ]);
        }
    }
}
