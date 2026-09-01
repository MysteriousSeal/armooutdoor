<?php

namespace App\Models;

use App\Observers\StockMovementObserver;
use App\Support\HtmlSanitizer;
use App\Support\ImageThumbnailer;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[ObservedBy(StockMovementObserver::class)]
#[Fillable([
    'category_id',
    'supplier_id',
    'available_at_supplier',
    'supplier_product_url',
    'supplier_reference',
    'supplier_price_cents',
    'markup_basis_points',
    'slug',
    'is_active',
    'ai_validated',
    'sku',
    'gtin',
    'weight_grams',
    'carrier_ids',
    'age_restricted',
    'image_may_vary',
    'name',
    'meta_title',
    'meta_description',
    'brand',
    'description',
    'characteristics',
    'filter_attributes',
    'price_cents',
    'quantity',
    'image',
    'featured',
    'sort_order',
])]
class Product extends Model
{
    /**
     * Ce qu'on regarde avant d'acheter, dans cet ordre.
     *
     * Les étiquettes viennent du vocabulaire que le catalogue réemploie d'une
     * fiche à l'autre — voir `docs/admin/make-products-ok.md`.
     */
    private const KEY_CHARACTERISTIC_LABELS = [
        'quantité',
        'diamètre',
        'dimensions',
        'taille',
        'longueur',
        'capacité',
        'contenance',
        'calibre',
        'matière',
        'couleur',
        'format',
        'distance conseillée',
        'type',
    ];

    /** TVA française applicable à l'achat fournisseur, en points de base. */
    public const VAT_RATE_BASIS_POINTS = 2000;

    /**
     * Plafond du champ Prix du formulaire. Une recommandation au-dessus
     * remplirait le champ d'une valeur hors bornes : le navigateur refuse
     * alors l'envoi sans rien afficher, et le bouton Enregistrer paraît mort.
     */
    public const MAX_PRICE_CENTS = 9999999;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            $product->recordSlug();
        });

        static::updated(function (Product $product): void {
            if ($product->wasChanged('is_active') && ! $product->is_active) {
                CartItem::query()->where('product_id', $product->id)->delete();
            }

            if ($product->wasChanged('slug')) {
                $product->recordSlug();
            }
        });
    }

    /**
     * Range l'adresse du jour et met les précédentes à la retraite.
     *
     * Une adresse déjà connue du produit est reprise telle quelle plutôt que
     * doublée : revenir à un ancien slug est un aller-retour, pas une
     * troisième adresse.
     */
    public function recordSlug(): void
    {
        $this->slugs()->whereNot('slug', $this->slug)->update(['is_active' => false]);

        $this->slugs()->updateOrCreate(
            ['slug' => $this->slug],
            ['is_active' => true],
        );
    }

    public function slugs(): HasMany
    {
        return $this->hasMany(ProductSlug::class);
    }

    /** Les adresses abandonnées, celles qui redirigent. */
    public function retiredSlugs(): HasMany
    {
        return $this->slugs()->retired();
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'characteristics' => 'array',
            'filter_attributes' => 'array',
            'price_cents' => 'integer',
            'supplier_price_cents' => 'integer',
            'markup_basis_points' => 'integer',
            'quantity' => 'integer',
            'weight_grams' => 'integer',
            'carrier_ids' => 'array',
            'age_restricted' => 'boolean',
            'image_may_vary' => 'boolean',
            'is_active' => 'boolean',
            'ai_validated' => 'boolean',
            'available_at_supplier' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /**
     * Keeps quantity mirroring the sum of variant stock. Call this after
     * anything changes a variant's quantity (a sale, a restock, an admin
     * edit) — quantity itself must never be treated as authoritative once
     * a product has variants.
     */
    /**
     * Les articles du blog qui mentionnent ce produit.
     *
     * Filtré par le même périmètre que le blog lui-même : un brouillon citant
     * un produit ne doit pas se trahir depuis sa fiche.
     */
    public function blogPosts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class)
            ->visible()
            ->orderByDesc('published_at');
    }

    public function reconcileQuantity(): void
    {
        if ($this->hasVariants()) {
            $this->update(['quantity' => $this->variants()->sum('quantity')]);
        }
    }

    /**
     * Le journal de stock du produit, déclinaisons comprises.
     *
     * L'identifiant départage les mouvements de la même seconde : sans lui,
     * « le dernier » n'a pas de sens, et c'est sur lui que repose la
     * détection de dérive.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Ce que ce produit a réellement coûté, TVA comprise, sur ses bons de
     * commande passés — pondéré par la quantité reçue, pas par le nombre de
     * bons : un bon de dix-neuf pièces pèse plus qu'un bon d'une seule.
     *
     * Une ligne commandée mais jamais arrivée n'entre pas dans le calcul :
     * un prix promis n'est pas un prix payé. Null si rien n'a encore été
     * reçu — il n'y a alors rien à moyenner.
     */
    public function averagePurchaseCostInclVatCents(): ?int
    {
        $lines = $this->receivedPurchaseOrderLines();
        $units = $lines->sum('quantity_received');

        if ($units === 0) {
            return null;
        }

        $totalInclVatCents = $lines->sum(
            fn (PurchaseOrderItem $line): int => $line->purchaseOrder->receivedLineTotalInclVatWithChargesCents($line),
        );

        return (int) round($totalInclVatCents / $units);
    }

    /** How many units the average above is drawn from. */
    public function receivedPurchaseUnits(): int
    {
        return $this->receivedPurchaseOrderLines()->sum('quantity_received');
    }

    /**
     * @return Collection<int, PurchaseOrderItem>
     */
    private function receivedPurchaseOrderLines(): Collection
    {
        // .items aussi : la part de remise et de frais d'une ligne se
        // calcule sur la quantité reçue de tout le bon, pas seulement la
        // sienne — sans quoi chaque ligne rechargerait son bon à part.
        return $this->purchaseOrderItems()
            ->with('purchaseOrder.items')
            ->where('quantity_received', '>', 0)
            ->get();
    }

    /**
     * The same average as averagePurchaseCostInclVatCents(), for many
     * products in one query — a list page reading it per row would ask
     * once per product otherwise.
     *
     * @param  iterable<int>  $productIds
     * @return array<int, int> product id => average cost incl. VAT, cents.
     *                         A product with nothing received is absent
     *                         from the array rather than mapped to null.
     */
    public static function averagePurchaseCostsInclVatCents(iterable $productIds): array
    {
        $ids = collect($productIds)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $lines = PurchaseOrderItem::query()
            ->whereIn('product_id', $ids)
            ->where('quantity_received', '>', 0)
            ->with('purchaseOrder.items')
            ->get()
            ->groupBy('product_id');

        return $lines->map(function (Collection $productLines): int {
            $units = $productLines->sum('quantity_received');

            $totalInclVatCents = $productLines->sum(
                fn (PurchaseOrderItem $line): int => $line->purchaseOrder->receivedLineTotalInclVatWithChargesCents($line),
            );

            return (int) round($totalInclVatCents / $units);
        })->all();
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    public function reviewsCount(): int
    {
        return $this->relationLoaded('reviews') ? $this->reviews->count() : $this->reviews()->count();
    }

    /**
     * Combien d'avis par note, de 5 à 1.
     *
     * Les notes sans avis valent zéro plutôt que de manquer : la répartition
     * se lit comme un histogramme, et une barre absente se confondrait avec
     * une barre courte.
     *
     * @return array<int, int>
     */
    public function ratingDistribution(): array
    {
        $counts = ($this->relationLoaded('reviews') ? $this->reviews : $this->reviews()->get())
            ->countBy('rating');

        return collect(range(5, 1))
            ->mapWithKeys(fn (int $stars): array => [$stars => (int) $counts->get($stars, 0)])
            ->all();
    }

    public function averageRating(): ?float
    {
        $average = $this->relationLoaded('reviews')
            ? $this->reviews->avg('rating')
            : $this->reviews()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function hasBeenReviewedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->reviews()->where('user_id', $user->id)->exists();
    }

    /**
     * The oldest shipped order belonging to the user that contains this
     * product and doesn't already have a review — i.e. the order a new
     * review would be attached to. A customer can review each qualifying
     * order once, so this keeps returning orders as long as any are left
     * unreviewed. Null once every eligible order has been reviewed.
     */
    public function eligibleOrderFor(?User $user): ?Order
    {
        if ($user === null) {
            return null;
        }

        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['shipped', 'in_transit', 'delivered'])
            ->whereHas('items', fn (Builder $query) => $query->where('product_id', $this->id))
            ->whereDoesntHave('reviews', fn (Builder $query) => $query->where('product_id', $this->id))
            ->oldest()
            ->first();
    }

    public function canBeReviewedBy(?User $user): bool
    {
        return $this->eligibleOrderFor($user) !== null;
    }

    /**
     * Les quelques caractéristiques qui décident d'un achat.
     *
     * Le tableau complet vit en bas de page, seize lignes toutes de même
     * poids ; celle qu'on vérifie avant de cliquer se trouve à la deuxième ou
     * à la quatorzième. On en remonte quelques-unes près du prix.
     *
     * L'ordre suit une liste d'étiquettes, celles que le catalogue réutilise
     * d'une fiche à l'autre. Une fiche qui n'en emploie aucune garde ses
     * premières lignes plutôt que rien : mieux vaut un choix approximatif que
     * pas de résumé du tout.
     *
     * @return array<int, array<string, string>>
     */
    public function keyCharacteristics(int $max = 4): array
    {
        $rows = collect($this->characteristics ?? [])
            ->filter(fn ($row): bool => filled($row['label'] ?? null) && filled($row['value'] ?? null))
            // Le poids du colis ferme le tableau complet : il renseigne le
            // port, il ne décide pas d'un achat.
            ->reject(fn (array $row): bool => Str::startsWith(Str::lower($row['label']), 'poids'))
            ->values();

        $ranked = $rows->sortBy(function (array $row) use ($rows): array {
            $rank = array_search(Str::lower($row['label']), self::KEY_CHARACTERISTIC_LABELS, true);

            // À égalité de rang, l'ordre du tableau départage : deux lignes
            // hors liste ne doivent pas s'inverser d'un affichage à l'autre.
            return [$rank === false ? PHP_INT_MAX : $rank, $rows->search($row, true)];
        });

        return $ranked->take($max)->values()->all();
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    /**
     * The name as a search result should carry it.
     *
     * Product names here describe the article in full — format, count, size,
     * colour, zones — and run past a hundred characters doing it. That is the
     * right name on the page and too long for a result, which truncates around
     * sixty. `meta_title` is where a shorter one is written for the products
     * that need it; the rest keep the name they already have.
     */
    public function metaTitle(): string
    {
        $override = trim((string) $this->meta_title);

        return $override !== '' ? $override : $this->localizedName();
    }

    /**
     * The maker, from its own column or from where it used to live.
     *
     * Brand was a « Marque » characteristic before it was a field. The old
     * entries were copied across and left in place, since the category
     * filters are built from them, so the column is read first and the
     * characteristic remains the fallback for anything not yet migrated.
     */
    public function brandName(): ?string
    {
        $brand = trim((string) $this->brand);

        if ($brand !== '') {
            return $brand;
        }

        foreach (array_merge($this->characteristics ?? [], $this->filter_attributes ?? []) as $entry) {
            if (($entry['label'] ?? '') === 'Marque') {
                $value = trim((string) ($entry['value'] ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Every brand the catalogue already uses, for the admin form to suggest.
     *
     * Keeps "ASG" and "ASG (Blaster)" from drifting further apart without
     * refusing a brand that has not been sold before.
     *
     * @return list<string>
     */
    public static function knownBrands(): array
    {
        return static::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->all();
    }

    /**
     * The description as a search result should carry it.
     *
     * Derived from the page by default, cut at the last whole sentence that
     * fits rather than at a fixed number of characters — which used to land
     * mid-word. `meta_description` overrides that for a product whose opening
     * sentences are not the ones worth showing.
     */
    public function metaDescription(): string
    {
        $override = trim((string) $this->meta_description);

        return $override !== '' ? $override : meta_description($this->localizedDescriptionText());
    }

    public function localizedDescription(): string
    {
        return HtmlSanitizer::forDisplay($this->localized('description'));
    }

    public function localizedDescriptionText(): string
    {
        return HtmlSanitizer::toPlainText($this->localized('description'));
    }

    public function discount(): HasOne
    {
        return $this->hasOne(Discount::class);
    }

    public function hasDiscount(): bool
    {
        $discount = $this->relationLoaded('discount') ? $this->discount : $this->discount()->first();

        return $discount !== null && $discount->isActive();
    }

    public function effectivePriceCents(): int
    {
        return $this->hasDiscount() ? $this->discount->apply($this->price_cents) : $this->price_cents;
    }

    public function formattedPrice(): string
    {
        return format_euros($this->effectivePriceCents());
    }

    public function formattedOriginalPrice(): string
    {
        return format_euros($this->price_cents);
    }

    public function inStock(): bool
    {
        return $this->quantity > 0;
    }

    public function isPurchasable(): bool
    {
        if ($this->hasVariants()) {
            return $this->variants->contains(fn (ProductVariant $variant): bool => $variant->is_active && ($variant->inStock() || $variant->isBackorderable()));
        }

        return $this->inStock() || $this->isBackorderable();
    }

    /**
     * Out of stock, but the supplier can still get it — sold as a
     * one-unit backorder. Only applies to products without variants.
     */
    /**
     * Les lignes de commande fournisseur encore en attente pour cet article.
     *
     * Ligne par ligne, pas commande par commande : une commande partiellement
     * reçue contient des articles arrivés et d'autres non, et seuls les
     * seconds sont encore en approvisionnement.
     */
    public function restockingPurchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn ($query) => $query->open());
    }

    /**
     * Ce qui est encore attendu, déclinaisons comprises.
     *
     * Renvoie la quantité manquante et les commandes fournisseur concernées,
     * pour que la fiche puisse dire « 12 en commande » plutôt que le seul
     * fait qu'un réassort existe.
     *
     * @return array{quantity: int, orders: Collection<int, PurchaseOrder>}
     */
    public function inboundStock(): array
    {
        $items = PurchaseOrderItem::query()
            ->with('purchaseOrder')
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn ($query) => $query->open())
            ->where(fn ($query) => $query
                ->where('product_id', $this->id)
                ->orWhereIn('product_variant_id', $this->variants()->pluck('id')))
            ->get();

        return [
            'quantity' => (int) $items->sum(fn (PurchaseOrderItem $item): int => max(0, $item->quantity_ordered - $item->quantity_received)),
            'orders' => $items->pluck('purchaseOrder')->filter()->unique('id')->values(),
        ];
    }

    /** Un réassort est en route pour cet article. */
    public function isRestocking(): bool
    {
        // Si la relation est déjà chargée, on ne repart pas en base : les
        // listings affichent des dizaines de produits d'affilée.
        if ($this->relationLoaded('restockingPurchaseItems')) {
            return $this->restockingPurchaseItems->isNotEmpty();
        }

        return $this->restockingPurchaseItems()->exists();
    }

    /** The wording printed on this product's label, when it has any. */
    public function label(): HasOne
    {
        return $this->hasOne(ProductLabel::class);
    }

    /**
     * What a label still needs before it can be printed.
     *
     * The wording lives on the product, the codes on the article — the product
     * itself when it has no variants, each variant when it has. An empty list
     * means the label can go.
     *
     * @return array<int, string>
     */
    public function labelRequirements(?ProductVariant $variant = null): array
    {
        $article = $variant ?? $this;

        return collect([
            'title' => filled($this->label?->title),
            'subtitle' => filled($this->label?->subtitle),
            'reference' => filled($article->sku),
            'barcode' => filled($article->gtin),
        ])->reject(fn (bool $present): bool => $present)->keys()->all();
    }

    /** Whether a label can be printed for this article as it stands. */
    public function labelIsPrintable(?ProductVariant $variant = null): bool
    {
        return $this->labelRequirements($variant) === [];
    }

    public function isBackorderable(): bool
    {
        return ! $this->isRestocking()
            && ! $this->hasVariants() && $this->supplier_id !== null && $this->available_at_supplier;
    }

    /**
     * How the shop can serve this product right now, in one word.
     *
     * A product sold in several sizes is read through its sizes: what matters
     * on a listing is whether a customer can buy something on that page, not
     * what the quantities add up to. The best state any active size can offer
     * is the one shown.
     *
     * @return 'in_stock'|'low_stock'|'restocking'|'at_supplier'|'out_of_stock'
     */
    public function availabilityState(): string
    {
        if ($this->hasVariants()) {
            $active = $this->variants->filter(fn (ProductVariant $variant): bool => $variant->is_active);

            return match (true) {
                $active->contains(fn (ProductVariant $variant): bool => $variant->quantity > 2) => 'in_stock',
                $active->contains(fn (ProductVariant $variant): bool => $variant->inStock()) => 'low_stock',
                // Le réassort passe devant « dispo fournisseur » : une taille
                // déjà commandée ne se recommande pas chez le fournisseur.
                $active->contains(fn (ProductVariant $variant): bool => $variant->isRestocking()) => 'restocking',
                $active->contains(fn (ProductVariant $variant): bool => $variant->isBackorderable()) => 'at_supplier',
                default => 'out_of_stock',
            };
        }

        return match (true) {
            $this->lowStock() => 'low_stock',
            $this->inStock() => 'in_stock',
            $this->isRestocking() => 'restocking',
            $this->isBackorderable() => 'at_supplier',
            default => 'out_of_stock',
        };
    }

    public function lowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= 2;
    }

    /**
     * A null carrier_ids means unrestricted — every carrier can ship this
     * product. Once set, only the listed carriers are allowed.
     */
    public function isCarrierAllowed(Carrier $carrier): bool
    {
        return $this->carrier_ids === null || in_array($carrier->id, $this->carrier_ids, true);
    }

    public function maxPurchasable(): int
    {
        if (! $this->inStock() && $this->isBackorderable()) {
            return 1;
        }

        return max(0, min(10, $this->quantity));
    }

    /**
     * Le prix de vente conseillé : prix d'achat HT, plus 20 % de TVA, plus la
     * marge voulue, puis arrondi au palier psychologique supérieur.
     *
     * Null sans prix d'achat : sans lui il n'y a rien à recommander. Une marge
     * absente vaut 0 %, ce qui donne au moins le prix de revient TTC.
     */
    public function recommendedPriceCents(): ?int
    {
        // Zéro se traite comme une absence : sans coût, il n'y a pas de marge
        // à calculer, et 0,49 € recommandé sur un article gratuit ressemble à
        // un bug plutôt qu'à un conseil.
        if ($this->supplier_price_cents === null || $this->supplier_price_cents === 0) {
            return null;
        }

        $withVat = $this->supplier_price_cents * (1 + self::VAT_RATE_BASIS_POINTS / 10000);
        $withMarkup = $withVat * (1 + ($this->markup_basis_points ?? 0) / 10000);

        return min(
            self::roundUpToPsychologicalPrice((int) ceil(round($withMarkup, 4))),
            self::MAX_PRICE_CENTS,
        );
    }

    /**
     * Remonte au prochain montant finissant par ,49 ou ,99 — jamais en
     * dessous, pour ne pas rogner la marge demandée. 12,39 donne 12,49 ;
     * 12,51 donne 12,99 ; un montant déjà sur un palier n'est pas touché.
     */
    public static function roundUpToPsychologicalPrice(int $cents): int
    {
        $euros = intdiv($cents, 100);
        $remainder = $cents % 100;

        return $remainder <= 49
            ? $euros * 100 + 49
            : $euros * 100 + 99;
    }

    public function imageUrl(): string
    {
        if (str_starts_with($this->image, 'https://') || str_starts_with($this->image, 'http://')) {
            return $this->image;
        }

        return asset('images/'.$this->image);
    }

    public function thumbnailUrl(): string
    {
        return ImageThumbnailer::urlFor($this->image);
    }

    private function localized(string $attribute): string
    {
        $value = $this->{$attribute};

        if (! is_array($value)) {
            return (string) $value;
        }

        return $value['fr'] ?? '';
    }
}
