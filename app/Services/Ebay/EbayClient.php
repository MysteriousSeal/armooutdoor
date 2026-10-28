<?php

namespace App\Services\Ebay;

use App\Models\EbayConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * The shop's way into its eBay seller account.
 *
 * eBay signs a seller in with OAuth2's authorization code grant: the admin
 * consents once on eBay, the code that comes back buys a refresh token good
 * for eighteen months, and that buys two-hour access tokens as they are
 * needed.
 *
 * Listings are read through the Trading API rather than the Inventory API:
 * the Inventory API only sees listings it created itself, and the shop's
 * listings were made by hand on eBay.fr.
 */
class EbayClient
{
    /**
     * What the shop asks the seller to consent to. Inventory and account are
     * asked now so that publishing, which needs the business policies, does
     * not mean signing in again.
     */
    private const SCOPES = [
        'https://api.ebay.com/oauth/api_scope',
        'https://api.ebay.com/oauth/api_scope/sell.inventory',
        'https://api.ebay.com/oauth/api_scope/sell.account',
    ];

    /** The Trading API's schema version these calls are written against. */
    private const COMPATIBILITY_LEVEL = '1355';

    /** GetMyeBaySelling's maximum page. */
    private const PAGE_SIZE = 200;

    /** An access token is renewed this many seconds before it lapses. */
    private const EXPIRY_MARGIN = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.ebay.client_id'))
            && filled(config('services.ebay.client_secret'))
            && filled(config('services.ebay.ru_name'));
    }

    /** Where the admin is sent to consent; eBay comes back with the state. */
    public function authorizeUrl(string $state): string
    {
        return $this->authHost().'/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.ebay.client_id'),
            'redirect_uri' => config('services.ebay.ru_name'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Trades the code eBay sent back for tokens, and keeps them. */
    public function connect(string $code): EbayConnection
    {
        $token = $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.ebay.ru_name'),
        ]);

        if (! filled($token['refresh_token'] ?? null)) {
            throw new RuntimeException('eBay sent no refresh token.');
        }

        // One account at a time: a new sign-in replaces the old one.
        EbayConnection::query()->delete();

        return EbayConnection::query()->create([
            'refresh_token' => $token['refresh_token'],
            'refresh_token_expires_at' => now()->addSeconds((int) ($token['refresh_token_expires_in'] ?? 0)),
            'access_token' => $token['access_token'] ?? null,
            'access_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0)),
        ]);
    }

    /** A live access token, renewed from the refresh token when it is due. */
    public function accessToken(): string
    {
        $connection = EbayConnection::current();

        if ($connection === null) {
            throw new RuntimeException('eBay is not connected.');
        }

        if ($connection->hasExpired()) {
            throw new RuntimeException('The eBay sign-in has expired. Connect again.');
        }

        if (filled($connection->access_token)
            && $connection->access_token_expires_at?->isAfter(now()->addSeconds(self::EXPIRY_MARGIN))) {
            return $connection->access_token;
        }

        $token = $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'scope' => implode(' ', self::SCOPES),
        ]);

        $connection->update([
            'access_token' => $token['access_token'],
            'access_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0)),
        ]);

        return $token['access_token'];
    }

    /**
     * Every active listing, page by page.
     *
     * @return list<array{item_id: string, title: string, url: ?string, sku: ?string, price_cents: int, currency: string, quantity: int, quantity_sold: int, variations: list<array{sku: ?string, quantity: int, quantity_sold: int, price_cents: int, specifics: array<string, string>}>}>
     */
    public function activeListings(): array
    {
        $listings = [];
        $size = self::PAGE_SIZE;

        // A guard against a page count that never ends.
        for ($page = 1, $pages = 1; $page <= $pages && $page <= 100; $page++) {
            $xml = $this->trading('GetMyeBaySelling', <<<XML
                <ActiveList>
                    <Include>true</Include>
                    <Pagination>
                        <EntriesPerPage>{$size}</EntriesPerPage>
                        <PageNumber>{$page}</PageNumber>
                    </Pagination>
                </ActiveList>
                <DetailLevel>ReturnAll</DetailLevel>
                XML);

            $pages = (int) ($xml->ActiveList->PaginationResult->TotalNumberOfPages ?? 1);

            foreach ($xml->ActiveList->ItemArray->Item ?? [] as $item) {
                $listings[] = $this->listing($item);
            }
        }

        return $listings;
    }

    /** @return array<string, mixed> */
    private function listing(SimpleXMLElement $item): array
    {
        $variations = [];

        foreach ($item->Variations->Variation ?? [] as $variation) {
            $specifics = [];

            foreach ($variation->VariationSpecifics->NameValueList ?? [] as $pair) {
                $specifics[(string) $pair->Name] = (string) $pair->Value;
            }

            $quantity = (int) $variation->Quantity;
            $sold = (int) ($variation->SellingStatus->QuantitySold ?? 0);

            $variations[] = [
                'sku' => $this->text($variation->SKU),
                // eBay's Quantity counts what has sold too.
                'quantity' => max(0, $quantity - $sold),
                'quantity_sold' => $sold,
                'price_cents' => $this->cents($variation->StartPrice),
                'specifics' => $specifics,
            ];
        }

        $sold = (int) ($item->SellingStatus->QuantitySold ?? 0);
        $available = isset($item->QuantityAvailable)
            ? (int) $item->QuantityAvailable
            : max(0, (int) $item->Quantity - $sold);
        $price = $item->SellingStatus->CurrentPrice ?? $item->BuyItNowPrice ?? $item->StartPrice;

        return [
            'item_id' => (string) $item->ItemID,
            'title' => (string) $item->Title,
            'url' => $this->text($item->ListingDetails->ViewItemURL ?? null),
            'sku' => $this->text($item->SKU ?? null),
            'price_cents' => $this->cents($price),
            'currency' => (string) ($price['currencyID'] ?? 'EUR'),
            'quantity' => $variations === [] ? $available : array_sum(array_column($variations, 'quantity')),
            'quantity_sold' => $variations === [] ? $sold : array_sum(array_column($variations, 'quantity_sold')),
            'variations' => $variations,
        ];
    }

    /**
     * Whether the seller's "out-of-stock option" is on. Without it, a
     * quantity of 0 ends the listing for good instead of hiding it.
     */
    public function outOfStockControl(): bool
    {
        $xml = $this->trading('GetUserPreferences', '<ShowOutOfStockControlPreference>true</ShowOutOfStockControlPreference>');

        return (string) $xml->OutOfStockControlPreference === 'true';
    }

    /**
     * Sets what is available to buy, for up to four listings or variations
     * at once, eBay's limit for the call. A variation is named by its SKU,
     * a plain listing by its item number alone.
     *
     * @param  list<array{item_id: string, sku: ?string, quantity: int}>  $changes
     */
    public function reviseQuantities(array $changes): void
    {
        $statuses = '';

        foreach ($changes as $change) {
            $sku = $change['sku'] === null ? '' : '<SKU>'.htmlspecialchars($change['sku'], ENT_XML1).'</SKU>';
            $statuses .= "<InventoryStatus><ItemID>{$change['item_id']}</ItemID>{$sku}<Quantity>{$change['quantity']}</Quantity></InventoryStatus>";
        }

        $this->trading('ReviseInventoryStatus', $statuses);
    }

    /** One Trading API call; a failure comes back as eBay's own words. */
    private function trading(string $call, string $body): SimpleXMLElement
    {
        $request = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <{$call}Request xmlns="urn:ebay:apis:eBLBaseComponents">
            {$body}
            </{$call}Request>
            XML;

        $response = Http::withHeaders([
            'X-EBAY-API-CALL-NAME' => $call,
            'X-EBAY-API-SITEID' => (string) config('services.ebay.site_id'),
            'X-EBAY-API-COMPATIBILITY-LEVEL' => self::COMPATIBILITY_LEVEL,
            'X-EBAY-API-IAF-TOKEN' => $this->accessToken(),
        ])
            ->withBody($request, 'text/xml')
            ->timeout(60)
            ->post($this->apiHost().'/ws/api.dll');

        if ($response->failed()) {
            throw new RuntimeException("eBay {$call} answered HTTP {$response->status()}.");
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            throw new RuntimeException("eBay {$call} sent an unreadable answer.");
        }

        // Warning still carries the data; only Failure means there is none.
        if (in_array((string) $xml->Ack, ['Failure', 'PartialFailure'], true)) {
            $messages = [];

            foreach ($xml->Errors ?? [] as $error) {
                $messages[] = (string) ($error->LongMessage ?: $error->ShortMessage);
            }

            throw new RuntimeException("eBay {$call} failed: ".(implode(' ', array_filter($messages)) ?: 'no reason given.'));
        }

        return $xml;
    }

    /** @return array<string, mixed> */
    private function token(array $form): array
    {
        $response = Http::asForm()
            ->withBasicAuth((string) config('services.ebay.client_id'), (string) config('services.ebay.client_secret'))
            ->post($this->apiHost().'/identity/v1/oauth2/token', $form);

        if ($response->failed() || ! filled($response->json('access_token'))) {
            $reason = $response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status();

            throw new RuntimeException('eBay refused the sign-in: '.$reason);
        }

        return $response->json();
    }

    private function sandbox(): bool
    {
        return config('services.ebay.environment') === 'sandbox';
    }

    private function authHost(): string
    {
        return $this->sandbox() ? 'https://auth.sandbox.ebay.com' : 'https://auth.ebay.com';
    }

    private function apiHost(): string
    {
        return $this->sandbox() ? 'https://api.sandbox.ebay.com' : 'https://api.ebay.com';
    }

    private function text(mixed $node): ?string
    {
        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }

    private function cents(mixed $amount): int
    {
        return (int) round(((float) (string) $amount) * 100);
    }
}
