<?php

use Illuminate\Support\Number;

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

if (! function_exists('format_euros')) {
    /**
     * Format an integer cent amount as euros in the current locale.
     */
    function format_euros(int $cents): string
    {
        return Number::currency($cents / 100, in: 'EUR', locale: app()->getLocale());
    }
}
