<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

if (! function_exists('localized_route')) {
    /**
     * Generate a named route. The site is French-only and routes no longer
     * carry a {locale} segment; the $locale parameter is kept as a no-op so
     * existing call sites don't need to change.
     */
    function localized_route(string $name, array $parameters = [], ?string $locale = null): string
    {
        return route($name, $parameters);
    }
}

if (! function_exists('format_person_name')) {
    /**
     * Format a person's name for display: last name in uppercase, first name
     * capitalized (French administrative convention).
     */
    function format_person_name(?string $firstName, ?string $lastName): string
    {
        $first = Str::title(trim((string) $firstName));
        $last = Str::upper(trim((string) $lastName));

        return trim("{$first} {$last}");
    }
}

if (! function_exists('format_bytes')) {
    /**
     * A file size a person can read: 145 MB rather than 152043520.
     *
     * Rounded to one decimal above a megabyte, none below: the exact byte
     * count of a backup tells nobody anything.
     */
    function format_bytes(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = max(0, $bytes);

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit >= 2 ? 1 : 0, ',', ' ').' '.$units[$unit];
    }
}

if (! function_exists('versioned_asset')) {
    /**
     * Appends the site version as a cache-busting query string, so a version
     * bump forces browsers to fetch fresh CSS instead of a stale cached copy.
     */
    function versioned_asset(string $path): string
    {
        return asset($path).'?v='.config('shop.version');
    }
}

if (! function_exists('format_euros')) {
    /**
     * Format an integer cent amount as euros in the current locale.
     */
    function format_euros(int $cents): string
    {
        return Number::currency($cents / 100, in: 'EUR', locale: app()->getLocale());
    }
}

if (! function_exists('admin_relative_date')) {
    /**
     * Une date relative pour l'administration, toujours en anglais.
     *
     * La boutique tourne en `fr`, l'administration est écrite en anglais, et
     * `diffForHumans()` suit la locale de l'application : sans forcer la
     * langue, « il y a 10 minutes » se glisse au milieu d'une page anglaise.
     * C'est arrivé trois fois — les remises, les codes promo, puis les places
     * de marché — d'où ce point de passage unique.
     */
    function admin_relative_date(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return Carbon::parse($date)->locale('en')->diffForHumans();
    }
}
