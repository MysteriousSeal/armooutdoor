<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * Appends a cache-busting query string, so a browser holding a stale copy
     * of a stylesheet or a script is made to fetch the new one.
     *
     * The stamp is the file's own modification time rather than the site
     * version: a deploy is a `git reset --hard`, which only touches the files
     * that actually changed, so each asset busts on its own and the rest stay
     * in the visitor's cache. It also means a fix shipped without a version
     * bump still reaches everyone. Anything unreadable falls back to the site
     * version, which is never worse than the old behaviour.
     */
    function versioned_asset(string $path): string
    {
        $file = public_path(ltrim($path, '/'));

        $stamp = is_file($file)
            ? filemtime($file)
            : config('shop.version');

        return asset($path).'?v='.$stamp;
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

if (! function_exists('paginated_canonical')) {
    /**
     * The canonical URL of a listing page, page number included.
     *
     * Every page of a category used to name page one as its canonical, which
     * told search engines that page two and beyond were not worth indexing.
     * On a catalogue of 268 products spread over 53 categories that hides most
     * of the shop: a paginated page is now the canonical version of itself.
     *
     * Sorting and filtering stay out of it. They reorder or narrow the same
     * products rather than showing different ones, so their URLs still point
     * back at the plain listing.
     */
    function paginated_canonical(string $url, LengthAwarePaginator $paginator): string
    {
        $page = $paginator->currentPage();

        // A page number typed past the end holds nothing worth indexing, so it
        // points back at the listing rather than at itself.
        if ($page <= 1 || $page > $paginator->lastPage()) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'page='.$page;
    }
}

if (! function_exists('paginated_title')) {
    /**
     * A listing title that names the page it is on.
     *
     * Now that page two stands on its own in the index, wearing page one's
     * title would file the two as copies of each other.
     */
    function paginated_title(string $title, LengthAwarePaginator $paginator): string
    {
        $page = $paginator->currentPage();

        if ($page <= 1 || $page > $paginator->lastPage()) {
            return $title;
        }

        return $title.' — '.__('store.pagination_page', ['page' => $page]);
    }
}
