<?php

namespace App\Services\Naturabuy;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Le strict nécessaire pour lire l'API NaturaBuy.
 *
 * Deux particularités de leur API sont encapsulées ici plutôt que répétées
 * chez les appelants :
 *
 * 1. L'en-tête `content-type: application/json` leur fait attendre un corps,
 *    même en GET et même quand tous les paramètres sont dans l'URL. Sans
 *    corps, la réponse est `INVALID_JSON`. On envoie donc `{}`.
 * 2. La pagination est par curseur : la réponse porte `nextCursor` tant qu'il
 *    reste des pages, et ne le porte plus à la dernière.
 */
class NaturabuyClient
{
    /** Leur maximum, et aussi leur défaut. */
    public const MAX_LIMIT = 100;

    public function __construct(
        private readonly ?string $token = null,
        private readonly ?string $baseUrl = null,
    ) {}

    /**
     * Toutes les annonces, page après page.
     *
     * @param  array<string, mixed>  $filters  category, internalcode, closed
     * @return list<array<string, mixed>>
     */
    public function items(array $filters = [], int $maxPages = 100): array
    {
        $items = [];
        $cursor = null;
        $pages = 0;

        do {
            $query = $filters + ['limit' => self::MAX_LIMIT];

            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $payload = $this->get($this->path('items_path', '/v2/items'), $query);

            foreach ($payload['items'] ?? [] as $item) {
                $items[] = $item;
            }

            $cursor = $payload['nextCursor'] ?? null;
            $pages++;

            // Garde-fou : un curseur qui ne bougerait plus boucle à l'infini.
        } while ($cursor !== null && $pages < $maxPages);

        return $items;
    }

    /**
     * Les commandes.
     *
     * @return list<array<string, mixed>>
     */
    public function orders(array $filters = []): array
    {
        $payload = $this->get($this->path('orders_path', '/v5/orders'), $filters);

        // Cette route renvoie un tableau nu, sans enveloppe.
        return array_is_list($payload) ? $payload : ($payload['orders'] ?? []);
    }

    /** @return array<mixed> */
    private function get(string $url, array $query = []): array
    {
        $response = $this->request()->send('GET', $url, [
            'query' => $query,
            // Le corps vide obligatoire, voir l'en-tête de classe.
            'body' => '{}',
        ]);

        return $this->decode($response);
    }

    private function request(): PendingRequest
    {
        $token = $this->token ?? (string) config('services.naturabuy.token');

        if ($token === '') {
            throw new RuntimeException('NATURABUY_TOKEN is not set.');
        }

        return Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    /** @return array<mixed> */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'NaturaBuy responded '.$response->status().': '.mb_substr($response->body(), 0, 200)
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new RuntimeException('NaturaBuy returned a body that is not JSON.');
        }

        return $decoded;
    }

    private function path(string $key, string $default): string
    {
        $base = rtrim((string) (config('services.naturabuy.base_url') ?: 'https://api.naturabuy.fr'), '/');

        return $base.(string) (config('services.naturabuy.'.$key) ?: $default);
    }
}
