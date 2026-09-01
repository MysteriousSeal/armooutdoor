<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * The shop as a business, for the search engines that ask who is selling.
 *
 * Products, categories, articles and the FAQ each declared what they were; the
 * company behind them never did. Nothing tied the catalogue to a business with
 * an address, a contact and a registration number — the things that separate a
 * shop from a page that happens to list prices.
 *
 * The details are read from the company settings the legal pages already use,
 * so the address published in the mentions légales and the one published here
 * cannot drift apart.
 */
class OrganizationSchema
{
    /**
     * The name the rest of the site's JSON-LD points at, so the shop is one
     * business referred to again rather than a new one declared per page.
     */
    public static function id(): string
    {
        return localized_route('home').'#organization';
    }

    /**
     * The site, as distinct from the business running it: a collection page
     * belongs to the one, and is published by the other.
     */
    public static function websiteId(): string
    {
        return localized_route('home').'#website';
    }

    /**
     * The shop as another page refers to it.
     *
     * The full node lives on the home page, where Google looks for it. A page
     * naming the shop as its publisher carries this instead: enough to stand
     * on its own if read alone, and the same `@id`, so the two are understood
     * as one business rather than two.
     *
     * @return array<string, string>
     */
    public static function reference(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::id(),
            'name' => config('app.name'),
            'logo' => asset('favicon.svg'),
        ];
    }

    /** @return array<string, mixed> */
    public static function for(CompanySetting $company): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            // A shop rather than a company in general: the subtype is what
            // says this organisation sells things.
            '@type' => 'OnlineStore',
            '@id' => self::id(),
            'name' => config('app.name'),
            'legalName' => self::stated($company, 'company_name'),
            'url' => localized_route('home'),
            'logo' => asset('favicon.svg'),
            'image' => asset('images/hero.webp'),
            'description' => __('store.meta_home'),
            'email' => self::stated($company, 'contact_email'),
            'telephone' => self::stated($company, 'phone'),
            'taxID' => self::stated($company, 'siret'),
            'vatID' => self::stated($company, 'vat_number'),
            'address' => self::address($company),
            'currenciesAccepted' => config('shop.currency'),
            'areaServed' => config('shop.customer_countries'),
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * A field the company has actually filled in.
     *
     * `CompanySetting::value()` hands back a bracketed placeholder for a blank
     * required field so the legal pages show what is missing. That is the
     * right answer for a page a human reads and the wrong one here: publishing
     * « [Numéro de TVA] » as a VAT number states something untrue about the
     * business. Anything unfilled is left out instead.
     */
    private static function stated(CompanySetting $company, string $field): ?string
    {
        $value = trim((string) $company->{$field});

        return $value === '' ? null : $value;
    }

    /**
     * The registered address, split into the parts a postal address has.
     *
     * It is stored as one free-form line — "22 Rue Anita Conti, 44300, Nantes"
     * — so the postal code is found by shape rather than by position, and a
     * line that does not divide cleanly is published whole rather than
     * guessed at.
     *
     * @return array<string, string>|null
     */
    private static function address(CompanySetting $company): ?array
    {
        $raw = self::stated($company, 'address');

        if ($raw === null) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $part): bool => $part !== '',
        ));

        $postalCode = null;

        foreach ($parts as $index => $part) {
            if (preg_match('/^\d{5}$/', $part) === 1) {
                $postalCode = $part;
                unset($parts[$index]);

                break;
            }
        }

        $parts = array_values($parts);

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => array_shift($parts),
            'postalCode' => $postalCode,
            'addressLocality' => implode(' ', $parts),
            'addressCountry' => 'FR',
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
