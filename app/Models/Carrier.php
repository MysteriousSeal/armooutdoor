<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'eta',
    'method',
    'price_cents',
    'max_weight_grams',
    'sort_order',
    'active',
])]
class Carrier extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'eta' => 'array',
            'method' => DeliveryMethod::class,
            'price_cents' => 'integer',
            'max_weight_grams' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    /** Le transporteur prend-il un colis de ce poids ? Sans limite : oui. */
    public function carriesWeight(int $weightGrams): bool
    {
        return $this->max_weight_grams === null || $weightGrams <= $this->max_weight_grams;
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CarrierPriceTier::class)->orderBy('min_weight_grams');
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    public function localizedDescription(): string
    {
        return $this->localized('description');
    }

    public function localizedEta(): string
    {
        return $this->localized('eta');
    }

    public function formattedPrice(): string
    {
        return format_euros($this->price_cents);
    }

    /**
     * The price for a cart of the given weight, based on this carrier's
     * price tiers (the highest tier whose min weight the cart meets or
     * exceeds). Falls back to the flat price_cents when no tiers are set.
     */
    public function effectivePriceCentsForWeight(int $weightGrams): int
    {
        $tier = $this->priceTiers()
            ->where('min_weight_grams', '<=', $weightGrams)
            ->reorder('min_weight_grams', 'desc')
            ->first();

        return $tier?->price_cents ?? $this->price_cents;
    }

    public function formattedStartingPrice(): string
    {
        return format_euros($this->effectivePriceCentsForWeight(0));
    }

    /**
     * Page de suivi de chaque transporteur, indexée par slug. Les deux offres
     * Chronopost partagent le même suivi, et Colissimo comme Lettre suivie
     * passent par l'outil de La Poste.
     *
     * :number est remplacé par le numéro de suivi, :postcode par le code
     * postal du destinataire — Mondial Relay le demande en plus du numéro.
     */
    private const TRACKING_URLS = [
        'colissimo-home' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=:number',
        'lettre-suivie' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=:number',
        'chronopost-home' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=:number',
        'relais-pickup' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=:number',
        'mondial-relay' => 'https://www.mondialrelay.fr/suivi-de-colis?numeroExpedition=:number&codePostal=:postcode',
    ];

    /**
     * The carrier's tracking page with no number filled in — where the
     * help page can send a visitor who has their number in hand. Null for
     * a slug with no known tracking tool.
     */
    public static function trackingHomeUrl(string $slug): ?string
    {
        $template = self::TRACKING_URLS[$slug] ?? null;

        return $template === null ? null : strtok($template, '?');
    }

    /**
     * Null quand ce transporteur n'a pas de page de suivi connue : le numéro
     * reste alors affiché en clair plutôt que de renvoyer nulle part.
     *
     * Null aussi quand le modèle réclame un code postal qu'on n'a pas : le
     * lien mènerait à un formulaire vide, ce qui est pire qu'un numéro à
     * recopier.
     */
    public function trackingUrlFor(?string $trackingNumber, ?string $postcode = null): ?string
    {
        if (! filled($trackingNumber) || ! isset(self::TRACKING_URLS[$this->slug])) {
            return null;
        }

        $template = self::TRACKING_URLS[$this->slug];

        if (str_contains($template, ':postcode') && ! filled($postcode)) {
            return null;
        }

        return str_replace(
            [':number', ':postcode'],
            [rawurlencode($trackingNumber), rawurlencode((string) $postcode)],
            $template,
        );
    }

    public function isRelay(): bool
    {
        return $this->method === DeliveryMethod::Relay;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'eta' => $this->eta,
            'method' => $this->method->value,
            'price_cents' => $this->price_cents,
        ];
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
