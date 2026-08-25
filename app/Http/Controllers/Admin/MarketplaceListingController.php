<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use App\Models\NaturabuyListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Naturabuy\NaturabuySynchronizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class MarketplaceListingController extends Controller
{
    /** Les places de marché, et ce qu'on sait de chacune. */
    public function index(): View
    {
        return view('admin.marketplaces.index', [
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
            'naturabuyCount' => $this->openListings()->count(),
            'naturabuySyncedAt' => NaturabuyListing::query()->max('synced_at'),
        ]);
    }

    /**
     * Rapatrie les annonces à la demande.
     *
     * Synchrone : quelques secondes d'attente, mais l'écran dit ensuite ce qui
     * s'est passé. Une erreur de leur côté remonte telle quelle plutôt que de
     * laisser croire à une réussite.
     */
    public function syncNaturabuy(NaturabuySynchronizer $synchronizer): RedirectResponse
    {
        try {
            $result = $synchronizer->sync(prune: true);
        } catch (Throwable $e) {
            // Par le sac d'erreurs plutôt que par une clé de session à part :
            // c'est ce que le gabarit sait déjà afficher.
            return back()->withErrors(['naturabuy' => 'NaturaBuy sync failed. '.$e->getMessage()]);
        }

        $message = $result['fetched'].' listings fetched: '
            .$result['created'].' added, '.$result['updated'].' updated';

        if ($result['deleted'] > 0) {
            $message .= ', '.$result['deleted'].' removed';
        }

        return back()->with('status', $message.'.');
    }

    /**
     * Le rapprochement entre un code NaturaBuy et un produit d'ici.
     *
     * Deux requêtes pour toute la page, sur les seuls codes affichés : les
     * résoudre ligne par ligne ferait cinquante allers-retours. Une
     * déclinaison renvoie vers son produit parent, puisque c'est lui qui a une
     * fiche à ouvrir.
     *
     * @param  Collection<int, NaturabuyListing>  $listings
     * @return array<string, int> code interne => id produit
     */
    private function catalogueMatches($listings): array
    {
        $codes = $listings->pluck('internalcode')->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return [];
        }

        // Une déclinaison compare sa propre quantité, pas celle de son parent :
        // le total du produit est la somme de toutes les tailles, et l'annonce
        // NaturaBuy n'en vend qu'une.
        $byVariant = ProductVariant::query()
            ->whereIn('sku', $codes)
            ->get(['sku', 'product_id', 'quantity'])
            ->toBase()
            ->mapWithKeys(fn (ProductVariant $v): array => [
                $v->sku => ['product_id' => $v->product_id, 'quantity' => $v->quantity],
            ]);

        $byProduct = Product::query()
            ->whereIn('sku', $codes)
            ->get(['id', 'sku', 'quantity'])
            ->toBase()
            ->mapWithKeys(fn (Product $p): array => [
                $p->sku => ['product_id' => $p->id, 'quantity' => $p->quantity],
            ]);

        // Le produit l'emporte sur la déclinaison si les deux portent le code.
        $exact = $byVariant->merge($byProduct)
            ->map(fn (array $m): array => $m + ['exact' => true])
            ->all();

        $matches = $exact + $this->prefixMatches($codes->reject(fn (string $c): bool => isset($exact[$c])));

        return $this->withProductNames($matches);
    }

    /**
     * Ajoute le nom du produit à chaque rapprochement.
     *
     * En une requête pour toute la page : une déclinaison renvoie le nom de son
     * parent, puisque c'est lui qui porte le libellé.
     *
     * @param  array<string, array{product_id: int, quantity: int, exact: bool}>  $matches
     * @return array<string, array{product_id: int, quantity: int, exact: bool, name: string}>
     */
    private function withProductNames(array $matches): array
    {
        $ids = collect($matches)->pluck('product_id')->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $names = Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Product $p): array => [$p->id => $p->localizedName()]);

        foreach ($matches as $code => $match) {
            $matches[$code]['name'] = (string) ($names[$match['product_id']] ?? '');
        }

        return $matches;
    }

    /**
     * Le repli : le code NaturaBuy est le préfixe de nos SKU de déclinaison.
     *
     * NaturaBuy vend une annonce par coloris, la taille étant choisie à
     * l'achat ; ici chaque taille est une déclinaison avec son propre SKU,
     * suffixé. Leur `M-SPORT-TEE-CAMOWHT-POLY` correspond donc à nos
     * `…-S`, `…-M` et `…-L`, et le produit parent n'a aucun SKU à opposer :
     * la règle du catalogue le lui retire dès qu'il a des déclinaisons.
     *
     * La quantité comparée est la somme des déclinaisons concernées, puisque
     * l'annonce unique les vend toutes.
     *
     * @param  Collection<int, string>  $codes
     * @return array<string, array{product_id: int, quantity: int, exact: bool}>
     */
    private function prefixMatches($codes): array
    {
        if ($codes->isEmpty()) {
            return [];
        }

        // Le tiret est exigé partout : sans lui, « ABC » réclamerait « ABCD ».
        $like = function (Builder $outer) use ($codes): void {
            foreach ($codes as $code) {
                $outer->orWhere('sku', 'like', $code.'-%');
            }
        };

        $candidates = ProductVariant::query()->whereNotNull('sku')->where($like)
            ->get(['sku', 'product_id', 'quantity'])
            ->map(fn (ProductVariant $v): array => [
                'sku' => (string) $v->sku, 'product_id' => (int) $v->product_id, 'quantity' => (int) $v->quantity,
            ])
            // Un produit sans déclinaison peut lui aussi porter un SKU suffixé :
            // le télémètre est vendu chez eux sous le code sans son coloris.
            ->concat(
                Product::query()->whereNotNull('sku')->where($like)
                    ->get(['id', 'sku', 'quantity'])
                    ->map(fn (Product $p): array => [
                        'sku' => (string) $p->sku, 'product_id' => (int) $p->id, 'quantity' => (int) $p->quantity,
                    ])
            );

        $matches = [];

        foreach ($codes as $code) {
            $group = $candidates->filter(fn (array $c): bool => str_starts_with($c['sku'], $code.'-'));

            if ($group->isEmpty()) {
                continue;
            }

            $matches[$code] = [
                'product_id' => $group->first()['product_id'],
                'quantity' => (int) $group->sum('quantity'),
                'exact' => false,
            ];
        }

        return $matches;
    }

    /**
     * Les annonces rapprochées d'un produit d'ici, ou l'inverse.
     *
     * Le rapprochement se fait en base plutôt qu'en PHP : filtrer un onglet
     * suppose de connaître le verdict pour toute la table, pas seulement pour
     * la page affichée. Une annonce sans code interne compte comme non
     * rapprochée, puisqu'il n'y a rien à retrouver.
     */
    private function whereMatched(Builder $query, bool $matched = true): Builder
    {
        $prefix = $this->prefixExpression($query);

        $exists = function (Builder $inner) use ($prefix): void {
            $inner->whereNotNull('internalcode')
                ->where(function (Builder $either) use ($prefix): void {
                    $either
                        ->whereExists(fn ($sub) => $sub->selectRaw(1)->from('products')
                            ->whereColumn('products.sku', 'naturabuy_listings.internalcode'))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw(1)->from('product_variants')
                            ->whereColumn('product_variants.sku', 'naturabuy_listings.internalcode'))
                        // Le repli par préfixe, voir catalogueMatches().
                        ->orWhereExists(fn ($sub) => $sub->selectRaw(1)->from('product_variants')
                            ->whereRaw('product_variants.sku LIKE '.$prefix))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw(1)->from('products')
                            ->whereRaw('products.sku LIKE '.$prefix));
                });
        };

        return $matched ? $query->where($exists) : $query->whereNot($exists);
    }

    /**
     * Les annonces dont la quantité ne correspond pas à la nôtre.
     *
     * Le calcul refait en SQL ce que `catalogueMatches()` fait en PHP, et pour
     * la même raison que les onglets de rapprochement : un onglet filtre toute
     * la table, pas seulement les cinquante lignes affichées.
     *
     * L'ordre des sources reprend celui du rapprochement : produit exact, puis
     * déclinaison exacte, puis la somme des SKU préfixés. Une annonce sans
     * correspondance n'a rien à comparer et reste dehors.
     */
    private function whereQuantityMismatch(Builder $query): Builder
    {
        $ours = $this->ourQuantityExpression($query);

        // Le `IS NOT NULL` est redondant — en SQL, `NULL <> x` vaut NULL et la
        // ligne tombe déjà — mais il dit l'intention à voix haute plutôt que de
        // la laisser reposer sur la logique ternaire.
        return $query
            ->whereNotNull('internalcode')
            ->whereRaw("({$ours}) IS NOT NULL")
            ->whereRaw("({$ours}) <> naturabuy_listings.quantity");
    }

    /**
     * Les annonces dont le titre diffère du nom du produit ici.
     *
     * Comparaison brute, sans normalisation : sur les données réelles, mettre
     * en minuscules et retirer les accents ne réconcilie aucune paire. Les
     * écarts sont de vraies différences de rédaction, pas de mise en forme.
     */
    private function whereNameMismatch(Builder $query): Builder
    {
        $ours = $this->ourNameExpression($query);

        return $query
            ->whereNotNull('internalcode')
            ->whereRaw("({$ours}) IS NOT NULL")
            ->whereRaw("({$ours}) <> naturabuy_listings.title");
    }

    /** Notre nom pour une annonce, exprimé en SQL. */
    private function ourNameExpression(Builder $query): string
    {
        $prefix = $this->prefixExpression($query);
        $name = $this->jsonNameExpression($query, 'p');

        // Même ordre de préférence que le rapprochement : produit exact,
        // déclinaison exacte, puis repli par préfixe. Une déclinaison renvoie
        // le nom de son parent, qui est celui qui porte le libellé.
        $sources = [
            "SELECT {$name} FROM products p WHERE p.sku = naturabuy_listings.internalcode LIMIT 1",
            "SELECT {$name} FROM products p JOIN product_variants v ON v.product_id = p.id"
                .' WHERE v.sku = naturabuy_listings.internalcode LIMIT 1',
            "SELECT {$name} FROM products p JOIN product_variants v ON v.product_id = p.id"
                ." WHERE v.sku LIKE {$prefix} LIMIT 1",
            "SELECT {$name} FROM products p WHERE p.sku LIKE {$prefix} LIMIT 1",
        ];

        return 'COALESCE(('.implode('), (', $sources).'))';
    }

    /**
     * Le nom français extrait de la colonne JSON, dans le dialecte de la base.
     *
     * `products.name` stocke `{"fr": "…"}` : SQLite lit ça avec
     * `json_extract`, MySQL veut en plus retirer les guillemets.
     */
    private function jsonNameExpression(Builder $query, string $alias): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? "json_extract({$alias}.name, '$.fr')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$alias}.name, '$.fr'))";
    }

    /** Notre quantité pour une annonce, exprimée en SQL. */
    private function ourQuantityExpression(Builder $query): string
    {
        $prefix = $this->prefixExpression($query);

        $exactProduct = 'SELECT p.quantity FROM products p WHERE p.sku = naturabuy_listings.internalcode LIMIT 1';
        $exactVariant = 'SELECT v.quantity FROM product_variants v WHERE v.sku = naturabuy_listings.internalcode LIMIT 1';
        // Le repli additionne les deux tables : une annonce peut couvrir
        // plusieurs déclinaisons, ou un produit au SKU suffixé.
        $prefixSum = "SELECT COALESCE((SELECT SUM(v2.quantity) FROM product_variants v2 WHERE v2.sku LIKE {$prefix}), 0)"
            ." + COALESCE((SELECT SUM(p2.quantity) FROM products p2 WHERE p2.sku LIKE {$prefix}), 0)"
            ." WHERE EXISTS (SELECT 1 FROM product_variants v3 WHERE v3.sku LIKE {$prefix})"
            ." OR EXISTS (SELECT 1 FROM products p3 WHERE p3.sku LIKE {$prefix})";

        return "COALESCE(({$exactProduct}), ({$exactVariant}), ({$prefixSum}))";
    }

    /**
     * « code interne suivi d'un tiret », dans le dialecte de la base.
     *
     * SQLite concatène avec `||`, MySQL veut `CONCAT()` : l'expression est
     * construite ici pour que le reste du filtre ignore la différence.
     */
    private function prefixExpression(Builder $query): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? "naturabuy_listings.internalcode || '-%'"
            : "CONCAT(naturabuy_listings.internalcode, '-%')";
    }

    /**
     * Les annonces encore en vente.
     *
     * Une annonce close est terminée chez eux : elle n'est plus achetable et
     * n'a rien à faire dans une liste qui sert à voir ce qui est en ligne. La
     * synchronisation continue de les enregistrer, mais aucune page ne les
     * montre — et aucun compteur ne les compte, sans quoi les chiffres et la
     * table se contrediraient.
     */
    private function openListings(): Builder
    {
        return NaturabuyListing::query()->where('closed', false);
    }

    public function naturabuy(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['in-stock', 'out-of-stock', 'in-catalogue', 'not-in-catalogue', 'qty-mismatch', 'name-mismatch'], true)
            ? $request->query('tab')
            : 'all';

        $search = trim((string) $request->query('search', ''));

        $listings = $this->openListings()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('internalcode', 'like', '%'.$search.'%')))
            ->when($tab === 'in-stock', fn (Builder $q) => $q->where('out_of_stock', false))
            ->when($tab === 'out-of-stock', fn (Builder $q) => $q->where('out_of_stock', true))
            ->when($tab === 'in-catalogue', fn (Builder $q) => $this->whereMatched($q))
            ->when($tab === 'not-in-catalogue', fn (Builder $q) => $this->whereMatched($q, false))
            ->when($tab === 'qty-mismatch', fn (Builder $q) => $this->whereQuantityMismatch($q))
            ->when($tab === 'name-mismatch', fn (Builder $q) => $this->whereNameMismatch($q))
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString();

        return view('admin.marketplaces.naturabuy', [
            'listings' => $listings,
            'catalogueMatches' => $this->catalogueMatches($listings->getCollection()),
            'tab' => $tab,
            'search' => $search,
            'allCount' => $this->openListings()->count(),
            'inStockCount' => $this->openListings()->where('out_of_stock', false)->count(),
            'outOfStockCount' => $this->openListings()->where('out_of_stock', true)->count(),
            'inCatalogueCount' => $this->whereMatched($this->openListings())->count(),
            'notInCatalogueCount' => $this->whereMatched($this->openListings(), false)->count(),
            'mismatchCount' => $this->whereQuantityMismatch($this->openListings())->count(),
            'nameMismatchCount' => $this->whereNameMismatch($this->openListings())->count(),
            'syncedAt' => NaturabuyListing::query()->max('synced_at'),
        ]);
    }
}
