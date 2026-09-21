<?php

namespace App\Services\Octopia;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The little of Octopia's Seller API the shop reads: its categories, and the
 * properties each one asks for.
 *
 * Octopia signs a seller in with OAuth2 client credentials: a token good for
 * two hours, sent as a Bearer header beside the seller's own id. The token is
 * kept until shortly before it lapses, so a page of calls asks for one once.
 * Cdiscount only takes products in French.
 */
class OctopiaClient
{
    private const LANGUAGE = 'fr-FR';

    /** Their maximum, and what the category list is read with. */
    private const PAGE_SIZE = 100;

    /** How long the category list is kept: it changes a few times a year. */
    private const CATEGORIES_TTL = 86400;

    /**
     * Octopia answers with the reference alone unless the fields are asked
     * for, comma-separated: without them there is no label, no level, and
     * nothing to filter on.
     */
    /**
     * How many pages are asked at once: the list runs to some eighty of them,
     * and one after the other takes longer than a web request is given.
     */
    private const CONCURRENCY = 8;

    /** Octopia takes at most this many offers in one upload. */
    private const OFFERS_PER_UPLOAD = 100;

    private const CATEGORY_FIELDS = 'label,level,isActive,isBrandMandatory,parentReferences';

    public function isConfigured(): bool
    {
        return filled(config('services.octopia.client_id'))
            && filled(config('services.octopia.client_secret'))
            && filled(config('services.octopia.seller_id'));
    }

    /**
     * The categories a product can be filed under: active, and level 3, the
     * only level Octopia takes a product at.
     *
     * The list is read page by page and kept, since it has no text search:
     * looking one up is a filter over what was kept.
     *
     * @return list<array{code: string, label: string, brand_mandatory: bool, parents: list<string>}>
     */
    public function categories(bool $fresh = false): array
    {
        $key = 'octopia.categories.'.$this->credentialsKey();

        if ($fresh) {
            Cache::forget($key);
        }

        $kept = Cache::get($key);

        if (is_array($kept) && $kept !== []) {
            return $kept;
        }

        $categories = (function (): array {
            $categories = [];

            $done = false;

            // A guard against a page that never comes back short.
            for ($first = 1; ! $done && $first <= 200; $first += self::CONCURRENCY) {
                foreach ($this->categoryPages($first, self::CONCURRENCY) as $items) {
                    foreach ($items as $item) {
                        if (($item['level'] ?? null) === 3 && ($item['isActive'] ?? true) && filled($item['categoryReference'] ?? null)) {
                            $categories[] = [
                                'code' => (string) $item['categoryReference'],
                                'label' => (string) ($item['label'] ?? $item['categoryReference']),
                                'brand_mandatory' => (bool) ($item['isBrandMandatory'] ?? false),
                                'parents' => array_map('strval', $item['parentReferences'] ?? []),
                            ];
                        }
                    }

                    // A short page is the last one: what was asked past it is empty.
                    if (count($items) < self::PAGE_SIZE) {
                        $done = true;

                        break;
                    }
                }
            }

            return $categories;
        })();

        // An empty list is not kept: it would hide a misread answer for a
        // day, when reading again is what puts it right.
        if ($categories !== []) {
            Cache::put($key, $categories, self::CATEGORIES_TTL);
        }

        return $categories;
    }

    /**
     * A run of category pages, asked together and returned in order.
     *
     * A page that fails in the batch is asked again on its own, with the
     * retries the client gives every call, before it is called a failure.
     *
     * @return list<list<array<string, mixed>>>
     */
    private function categoryPages(int $first, int $count): array
    {
        $token = $this->token();
        $url = rtrim((string) config('services.octopia.base_url'), '/').'/categories';
        $query = fn (int $page): array => ['pageIndex' => $page, 'pageSize' => self::PAGE_SIZE, 'fields' => self::CATEGORY_FIELDS];

        $responses = Http::pool(function (Pool $pool) use ($first, $count, $token, $url, $query): array {
            $requests = [];

            for ($page = $first; $page < $first + $count; $page++) {
                $requests[] = $pool->as((string) $page)
                    ->withToken($token)
                    ->withHeaders(['SellerId' => (string) config('services.octopia.seller_id'), 'Accept-Language' => self::LANGUAGE])
                    ->acceptJson()
                    ->timeout(30)
                    ->get($url, $query($page));
            }

            return $requests;
        });

        $pages = [];

        for ($page = $first; $page < $first + $count; $page++) {
            $response = $responses[(string) $page] ?? null;

            $pages[] = $response instanceof Response && $response->successful() && is_array($response->json())
                ? ($response->json()['items'] ?? [])
                : ($this->get('/categories', $query($page))['items'] ?? []);
        }

        return $pages;
    }

    /**
     * Send a batch of products to Octopia's shared catalogue.
     *
     * Octopia takes them and answers with a package id; whether each was
     * refused or integrated is only known by asking for the report.
     *
     * @param  list<array<string, mixed>>  $products
     * @return string the package id
     */
    public function submitProducts(array $products): string
    {
        $response = $this->call('POST', '/products-integration', body: ['products' => $products]);
        $packageId = $response->json('packageId') ?? $response->header('packageId');

        if (blank($packageId)) {
            throw new RuntimeException('Octopia took the products but gave no package id: '.mb_substr($response->body(), 0, 200));
        }

        return (string) $packageId;
    }

    /**
     * What Octopia made of each product of a package.
     *
     * @return list<array<string, mixed>>
     */
    public function productReports(string $packageId): array
    {
        $items = [];

        for ($page = 1; $page <= 50; $page++) {
            $batch = $this->get('/products-integration-reports', ['packageId' => $packageId, 'pageIndex' => $page, 'pageSize' => 100])['items'] ?? [];
            $items = array_merge($items, $batch);

            if (count($batch) < 100) {
                break;
            }
        }

        return $items;
    }

    /**
     * Put offers on sale: a price, a stock and a delivery for products that
     * are already in Octopia's catalogue.
     *
     * An offer package takes three calls. It is opened, the offers are put in
     * it, a hundred at a time, and it is closed by marking it Ready: only then
     * does Octopia process it, and no offer can be added after.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return string the package id
     */
    public function submitOffers(array $offers): string
    {
        $created = $this->call(
            'POST',
            '/offer-packages',
            body: ['packageType' => 'Upsert'],
            headers: ['salesChannelId' => (string) config('services.octopia.sales_channel_id')],
        );

        // The id is in the Location the package was created at, not in a body.
        $location = (string) ($created->header('Content-Location') ?: $created->header('Location'));
        $packageId = trim((string) basename(rtrim($location, '/')));

        if ($packageId === '' || $packageId === '.') {
            throw new RuntimeException('Octopia opened an offer package but gave no id for it.');
        }

        try {
            foreach (array_chunk($offers, self::OFFERS_PER_UPLOAD) as $chunk) {
                $this->call('POST', '/offer-packages/'.rawurlencode($packageId).'/offer-requests', body: $chunk);
            }

            $this->waitForPackage($packageId);
            $this->call('PATCH', '/offer-packages/'.rawurlencode($packageId), body: ['state' => 'Ready']);
        } catch (RuntimeException $e) {
            // The package exists on Octopia's side, half filled: say which.
            throw new RuntimeException($e->getMessage().' (offer package '.$packageId.' was opened and not completed)', 0, $e);
        }

        return $packageId;
    }

    /**
     * Octopia takes the uploads in before it will close the package: it says
     * WaitingForCompletion once it has them. A package that does not say so
     * in a few seconds is not closed.
     */
    private function waitForPackage(string $packageId): void
    {
        for ($try = 1; $try <= 5; $try++) {
            try {
                $package = $this->get('/offer-packages/'.rawurlencode($packageId));
            } catch (RuntimeException) {
                // Not being able to read the state is not a reason to give up
                // on closing it: Octopia refuses the close if it is not time.
                return;
            }

            $state = $package['status'] ?? $package['state'] ?? null;

            if ($state === null || $state === 'WaitingForCompletion') {
                return;
            }

            if ($try < 5) {
                sleep(1);
            }
        }

        throw new RuntimeException('Octopia has not finished taking the offers in.');
    }

    /**
     * What Octopia made of each offer of a package.
     *
     * @return list<array<string, mixed>>
     */
    public function offerResults(string $packageId): array
    {
        $items = [];
        $response = $this->call('GET', '/offer-packages/'.rawurlencode($packageId).'/offer-requests-results', ['limit' => 100]);

        // Cursor pagination: each answer says, in a Link header, where the
        // next one is.
        for ($page = 1; $page <= 50; $page++) {
            $items = array_merge($items, (array) ($response->json('items') ?? []));

            if (preg_match('/<([^>]+)>\s*;\s*rel="?next"?/i', (string) $response->header('Link'), $match) !== 1) {
                break;
            }

            $response = $this->request()->get($match[1]);

            if ($response->failed()) {
                throw new RuntimeException($this->refusal($response));
            }
        }

        return $items;
    }

    /**
     * One category by its 6-character code.
     *
     * @return array{code: string, label: string, brand_mandatory: bool}
     */
    public function category(string $code): array
    {
        $item = $this->get('/categories/'.rawurlencode($code));

        if (blank($item['categoryReference'] ?? null)) {
            throw new RuntimeException('Octopia knows no category '.$code.'.');
        }

        return [
            'code' => (string) $item['categoryReference'],
            'label' => (string) ($item['label'] ?? $item['categoryReference']),
            'brand_mandatory' => (bool) ($item['isBrandMandatory'] ?? false),
        ];
    }

    /**
     * What the category asks a product for, as Octopia words it.
     *
     * @return list<array<string, mixed>>
     */
    public function properties(string $code): array
    {
        return array_values($this->get('/categories/'.rawurlencode($code).'/properties')['items'] ?? []);
    }

    /** @return array<mixed> */
    private function get(string $path, array $query = []): array
    {
        $decoded = $this->call('GET', $path, $query)->json();

        if (! is_array($decoded)) {
            throw new RuntimeException('Octopia returned a body that is not JSON.');
        }

        return $decoded;
    }

    private function call(string $method, string $path, array $query = [], array $body = [], array $headers = []): Response
    {
        $response = $this->send($method, $path, $query, $body, $headers);

        // A token can lapse or be revoked before its time: ask for another,
        // once, before calling it a refusal.
        if ($response->status() === 401) {
            Cache::forget($this->tokenKey());
            $response = $this->send($method, $path, $query, $body, $headers);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->refusal($response));
        }

        return $response;
    }

    /**
     * What Octopia said, in a sentence: its own title or detail when the
     * answer is a problem document, the start of the body otherwise.
     */
    private function refusal(Response $response): string
    {
        $said = $response->json('detail') ?: $response->json('title') ?: $response->json('message');

        return 'Octopia responded '.$response->status().': '.(is_string($said) && $said !== '' ? $said : mb_substr($response->body(), 0, 300));
    }

    /**
     * Where an offer package stands: WaitingForCompletion, Ready,
     * IntegrationPending, then Integrated once Octopia has processed it. Its
     * results are not available before that.
     */
    public function offerPackageState(string $packageId): ?string
    {
        $state = $this->get('/offer-packages/'.rawurlencode($packageId))['state'] ?? null;

        return is_string($state) ? $state : null;
    }

    private function send(string $method, string $path, array $query, array $body, array $headers = []): Response
    {
        $url = rtrim((string) config('services.octopia.base_url'), '/').$path;

        // A write is never sent twice on its own: a lost answer is not a lost
        // request, and the batch would be submitted again.
        return match ($method) {
            'POST' => $this->request(retry: false)->withHeaders($headers)->post($url, $body),
            'PATCH' => $this->request(retry: false)->withHeaders($headers)->patch($url, $body),
            default => $this->request()->withHeaders($headers)->get($url, $query),
        };
    }

    private function request(bool $retry = true): PendingRequest
    {
        return Http::withToken($this->token())
            ->withHeaders([
                'SellerId' => (string) config('services.octopia.seller_id'),
                'Accept-Language' => self::LANGUAGE,
            ])
            ->acceptJson()
            ->timeout(30)
            ->retry($retry ? 2 : 1, 500, throw: false);
    }

    private function token(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OCTOPIA_CLIENT_ID, OCTOPIA_CLIENT_SECRET and OCTOPIA_SELLER_ID are not all set.');
        }

        $cached = Cache::get($this->tokenKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(30)->post((string) config('services.octopia.auth_url'), [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.octopia.client_id'),
            'client_secret' => config('services.octopia.client_secret'),
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Octopia refused the credentials ('.$response->status().'). Check the client id and secret.');
        }

        $token = (string) $response->json('access_token');

        // Two minutes short of the lapse, so a token is never sent as it dies.
        Cache::put($this->tokenKey(), $token, max(60, (int) $response->json('expires_in', 7200) - 120));

        return $token;
    }

    private function tokenKey(): string
    {
        return 'octopia.token.'.$this->credentialsKey();
    }

    /** Keyed by who is signed in, so changing the credentials drops what was kept. */
    private function credentialsKey(): string
    {
        return sha1(config('services.octopia.client_id').'|'.config('services.octopia.seller_id'));
    }
}
