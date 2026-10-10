<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\VintedItem;
use App\Models\VintedItemLot;
use App\Models\VintedItemSale;
use App\Support\ImageThumbnailer;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The articles sold on Vinted only: a list kept by hand.
 *
 * None of them is a product, so none reaches the storefront. Nothing leaves
 * from here either: Vinted opens no API, and a sale made there is reported
 * with the Sold button, piece by piece, with the price it went for.
 */
class VintedItemController extends Controller
{
    /** Two tabs: what is left to sell, and what has all gone. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'sold-out' ? 'sold-out' : 'available';

        $items = VintedItem::query()
            // For the Sold and Profit columns, in one query for the page.
            ->with('sales')
            ->{$tab === 'sold-out' ? 'soldOut' : 'available'}()
            ->orderByDesc($tab === 'sold-out' ? 'updated_at' : 'id')
            ->get();

        return view('admin.marketplaces.vinted', [
            'tab' => $tab,
            'items' => $items,
            'availableCount' => VintedItem::query()->available()->count(),
            'soldOutCount' => VintedItem::query()->soldOut()->count(),
            'piecesLeft' => (int) VintedItem::query()->sum('quantity'),
        ]);
    }

    public function create(): View
    {
        return view('admin.marketplaces.vinted-form', ['item' => new VintedItem]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:8192'],
            'purchase_total' => ['required', 'numeric', 'min:0', 'max:500000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $image = $this->storeImage($request->file('image'), $data['title']);

        // The item and its first lot together: totals with no lot under
        // them would have a history that does not add up.
        $item = DB::transaction(function () use ($data, $image): VintedItem {
            $item = VintedItem::query()->create([
                'title' => $data['title'],
                'image' => $image,
                'purchase_total_cents' => $this->cents($data['purchase_total']),
                'lot_quantity' => (int) $data['quantity'],
                'quantity' => (int) $data['quantity'],
            ]);

            $item->lots()->create([
                'quantity' => $item->lot_quantity,
                'purchase_total_cents' => $item->purchase_total_cents,
            ]);

            return $item;
        });

        AdminActivityLog::record('vinted_item.created', $item, 'Added Vinted item '.$item->title);

        return redirect()
            ->route('admin.marketplaces.vinted')
            ->with('status', 'Vinted item added.');
    }

    public function edit(VintedItem $vintedItem): View
    {
        return view('admin.marketplaces.vinted-form', ['item' => $vintedItem->load('lots', 'sales')]);
    }

    /**
     * The title, the photo and the link to the listing only. What was paid and how many there are
     * change through the lots and the Sold button, never by typing over them.
     */
    public function update(Request $request, VintedItem $vintedItem): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // Optional here: the photo already on file stays unless replaced.
            'image' => [filled($vintedItem->image) ? 'nullable' : 'required', 'image', 'max:8192'],
            'vinted_url' => ['nullable', 'string', 'max:500', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                // One of Vinted's own sites, whatever the country: a link
                // pasted from elsewhere is a slip, not a listing.
                $host = (string) parse_url((string) $value, PHP_URL_HOST);

                if (! preg_match('/^(www\.)?vinted\.[a-z]{2,3}(\.[a-z]{2})?$/i', $host)) {
                    $fail('The Vinted link must be an address on Vinted, such as https://www.vinted.fr/items/…');
                }
            }],
        ]);

        $changes = ['title' => $data['title'], 'vinted_url' => $data['vinted_url'] ?? null];

        $replaced = null;

        if ($request->hasFile('image')) {
            $replaced = $vintedItem->image;
            $changes['image'] = $this->storeImage($request->file('image'), $data['title']);
        }

        $vintedItem->update($changes);

        // The old file goes last, once the new one is on record: the other
        // way round, a failed save would leave the item with no photo.
        $this->deleteImage($replaced);

        AdminActivityLog::record('vinted_item.updated', $vintedItem, 'Updated Vinted item '.$vintedItem->title);

        return redirect()
            ->route('admin.marketplaces.vinted.items.edit', $vintedItem)
            ->with('status', 'Vinted item saved.');
    }

    /** More of the same article bought: a new lot, added to the totals. */
    public function addLot(Request $request, VintedItem $vintedItem): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'purchase_total' => ['required', 'numeric', 'min:0', 'max:500000'],
        ]);

        $quantity = (int) $data['quantity'];
        $cents = $this->cents($data['purchase_total']);

        DB::transaction(function () use ($vintedItem, $quantity, $cents): void {
            $vintedItem->lots()->create(['quantity' => $quantity, 'purchase_total_cents' => $cents]);

            // Increments rather than assignments, so a Sold press landing
            // at the same moment is not written over.
            VintedItem::query()->whereKey($vintedItem->id)->update([
                'lot_quantity' => DB::raw('lot_quantity + '.$quantity),
                'quantity' => DB::raw('quantity + '.$quantity),
                'purchase_total_cents' => DB::raw('purchase_total_cents + '.$cents),
            ]);
        });

        AdminActivityLog::record('vinted_item.lot_added', $vintedItem, 'Added '.$quantity.' to Vinted item '.$vintedItem->title);

        return redirect()
            ->route('admin.marketplaces.vinted.items.edit', $vintedItem)
            ->with('status', $quantity.' added to the stock.');
    }

    /**
     * A lot entered by mistake, taken back off the totals.
     *
     * The first lot stays: it is the item itself. And a lot whose pieces
     * have already sold cannot go, or the stock would read below zero.
     */
    public function removeLot(VintedItem $vintedItem, VintedItemLot $vintedLot): RedirectResponse
    {
        abort_unless($vintedLot->vinted_item_id === $vintedItem->id, 404);

        if ($vintedLot->id === $vintedItem->lots()->min('id')) {
            return back()->withErrors(['vinted' => 'The first lot cannot be removed. Remove the item instead.']);
        }

        $removed = DB::transaction(function () use ($vintedItem, $vintedLot): bool {
            // Guarded in the query, like Sold: the stock has to cover the lot
            // at the moment it is taken off.
            $taken = VintedItem::query()
                ->whereKey($vintedItem->id)
                ->where('quantity', '>=', $vintedLot->quantity)
                ->update([
                    'lot_quantity' => DB::raw('lot_quantity - '.$vintedLot->quantity),
                    'quantity' => DB::raw('quantity - '.$vintedLot->quantity),
                    'purchase_total_cents' => DB::raw('purchase_total_cents - '.$vintedLot->purchase_total_cents),
                ]);

            if ($taken === 0) {
                return false;
            }

            $vintedLot->delete();

            return true;
        });

        if (! $removed) {
            return back()->withErrors(['vinted' => 'This lot cannot be removed: some of its '.$vintedLot->quantity.' pieces have already sold.']);
        }

        AdminActivityLog::record('vinted_item.lot_removed', $vintedItem, 'Removed a lot of '.$vintedLot->quantity.' from Vinted item '.$vintedItem->title);

        return redirect()
            ->route('admin.marketplaces.vinted.items.edit', $vintedItem)
            ->with('status', 'Lot removed.');
    }

    /**
     * One piece sold on Vinted, at the price it went for. No order is
     * written: this list stands apart from the shop's sales.
     */
    public function sold(Request $request, VintedItem $vintedItem): RedirectResponse
    {
        $data = $request->validate([
            // Vinted caps at 500,000 €; past that it is a typing slip.
            'price' => ['required', 'numeric', 'min:0', 'max:500000'],
        ]);

        $taken = DB::transaction(function () use ($vintedItem, $data): bool {
            // Guarded in the query, so two presses landing together cannot
            // take the count below zero.
            $taken = VintedItem::query()
                ->whereKey($vintedItem->id)
                ->where('quantity', '>', 0)
                ->decrement('quantity');

            if ($taken === 0) {
                return false;
            }

            $vintedItem->sales()->create(['price_cents' => $this->cents($data['price'])]);

            return true;
        });

        if (! $taken) {
            return back()->withErrors(['vinted' => $vintedItem->title.' is already sold out.']);
        }

        $vintedItem->refresh();

        AdminActivityLog::record(
            'vinted_item.sold',
            $vintedItem,
            'Sold one '.$vintedItem->title.' on Vinted for '.format_euros($this->cents($data['price'])),
        );

        return back()->with('status', $vintedItem->isSoldOut()
            ? 'One sold: '.$vintedItem->title.' is now sold out.'
            : 'One sold: '.$vintedItem->quantity.' left of '.$vintedItem->title.'.');
    }

    /** A sale reported by mistake: its line goes, and its piece comes back. */
    public function removeSale(VintedItem $vintedItem, VintedItemSale $vintedSale): RedirectResponse
    {
        abort_unless($vintedSale->vinted_item_id === $vintedItem->id, 404);

        DB::transaction(function () use ($vintedItem, $vintedSale): void {
            $vintedSale->delete();

            // Never past what was bought, whatever the history says.
            VintedItem::query()
                ->whereKey($vintedItem->id)
                ->whereColumn('quantity', '<', 'lot_quantity')
                ->increment('quantity');
        });

        $vintedItem->refresh();

        AdminActivityLog::record('vinted_item.sale_removed', $vintedItem, 'Removed one sale of '.$vintedItem->title.' on Vinted');

        return redirect()
            ->route('admin.marketplaces.vinted.items.edit', $vintedItem)
            ->with('status', 'Sale removed: '.$vintedItem->quantity.' left of '.$vintedItem->title.'.');
    }

    public function destroy(VintedItem $vintedItem): RedirectResponse
    {
        $title = $vintedItem->title;

        $this->deleteImage($vintedItem->image);
        // Said here rather than left to the foreign key alone, which SQLite
        // only follows when it is switched on.
        $vintedItem->lots()->delete();
        $vintedItem->sales()->delete();
        $vintedItem->delete();

        AdminActivityLog::record('vinted_item.deleted', null, 'Removed Vinted item '.$title);

        return redirect()
            ->route('admin.marketplaces.vinted')
            ->with('status', 'Vinted item removed.');
    }

    private function cents(mixed $euros): int
    {
        return (int) round((float) $euros * 100);
    }

    private function storeImage(UploadedFile $file, string $title): string
    {
        $directory = public_path('images/vinted-items');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // The extension is read off the file's content, never off the name
        // it was sent under: this lands in a public folder.
        $name = (Str::slug($title) ?: 'item').'-'.Str::lower(Str::random(6)).'.'.($file->extension() ?: 'jpg');
        $file->move($directory, $name);

        // Same treatment as a Vinted listing photo: the framing is kept and
        // only the smallest side is set.
        $relativePath = ImageThumbnailer::normalizeMinSide('vinted-items/'.$name) ?? 'vinted-items/'.$name;

        ImageThumbnailer::generate($relativePath);

        return $relativePath;
    }

    /** The file goes with the row, and its thumbnail with it. */
    private function deleteImage(?string $image): void
    {
        if (blank($image)) {
            return;
        }

        foreach ([public_path('images/'.$image), ImageThumbnailer::absoluteThumbnailPath($image)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
