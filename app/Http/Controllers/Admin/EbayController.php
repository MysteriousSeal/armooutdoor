<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EbayConnection;
use App\Models\EbayListing;
use App\Services\Ebay\EbayCatalogue;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\EbayStockPusher;
use App\Services\Ebay\EbaySynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * eBay in the admin: signing the seller account in, and the listings read
 * from it, set beside the catalogue by SKU.
 */
class EbayController extends Controller
{
    public function index(Request $request, EbayClient $client, EbayCatalogue $catalogue): View
    {
        $search = trim((string) $request->query('search', ''));

        $listings = EbayListing::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($either) => $either
                ->where('title', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString();

        return view('admin.marketplaces.ebay', [
            'configured' => $client->isConfigured(),
            'connection' => EbayConnection::current(),
            'listings' => $listings,
            'catalogue' => $catalogue->forSkus($listings->getCollection()->flatMap->skus()->unique()->values()->all()),
            'count' => EbayListing::query()->count(),
            'syncedAt' => EbayListing::query()->max('synced_at'),
            'search' => $search,
        ]);
    }

    /** Sends the admin to eBay to consent, with a state to check on return. */
    public function connect(Request $request, EbayClient $client): RedirectResponse
    {
        if (! $client->isConfigured()) {
            return back()->withErrors(['ebay' => 'Set EBAY_CLIENT_ID, EBAY_CLIENT_SECRET and EBAY_RU_NAME first.']);
        }

        $state = Str::random(40);
        $request->session()->put('ebay_oauth_state', $state);

        return redirect()->away($client->authorizeUrl($state));
    }

    /** Where eBay sends the admin back, with a code or a refusal. */
    public function callback(Request $request, EbayClient $client): RedirectResponse
    {
        $expected = $request->session()->pull('ebay_oauth_state');
        $back = redirect()->route('admin.marketplaces.ebay');

        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $back->withErrors(['ebay' => 'The eBay sign-in could not be checked. Try connecting again.']);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $back->withErrors(['ebay' => 'eBay did not grant access.']);
        }

        try {
            $client->connect($code);
        } catch (Throwable $e) {
            return $back->withErrors(['ebay' => $e->getMessage()]);
        }

        return $back->with('status', 'eBay connected.');
    }

    public function disconnect(): RedirectResponse
    {
        EbayConnection::query()->delete();

        return back()->with('status', 'eBay disconnected. The listings already read stay until the next sync.');
    }

    public function sync(EbaySynchronizer $synchronizer): RedirectResponse
    {
        try {
            $result = $synchronizer->sync();
        } catch (Throwable $e) {
            return back()->withErrors(['ebay' => 'eBay sync failed. '.$e->getMessage()]);
        }

        $message = $result['fetched'].' listings fetched: '
            .$result['created'].' added, '.$result['updated'].' updated';

        if ($result['deleted'] > 0) {
            $message .= ', '.$result['deleted'].' removed';
        }

        return back()->with('status', $message.'.');
    }

    /** What the schedule does every fifteen minutes, now: read, then send. */
    public function pushStock(EbaySynchronizer $synchronizer, EbayStockPusher $pusher): RedirectResponse
    {
        try {
            $synchronizer->sync();
            $result = $pusher->push();
        } catch (Throwable $e) {
            return back()->withErrors(['ebay' => 'eBay stock push failed. '.$e->getMessage()]);
        }

        $message = EbayStockPusher::summary($result);

        return $result['failed'] > 0
            ? back()->withErrors(['ebay' => $message])
            : back()->with('status', $message);
    }
}
