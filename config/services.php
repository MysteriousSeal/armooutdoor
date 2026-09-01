<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'admin_api' => [
        'token' => env('ADMIN_API_TOKEN'),
    ],

    'naturabuy' => [
        'token' => env('NATURABUY_TOKEN'),
        'base_url' => env('NATURABUY_BASE_URL', 'https://api.naturabuy.fr'),
        // Les deux ressources ne vivent pas sur la même version de l'API :
        // les annonces en v2, les commandes en v5. D'où deux chemins séparés
        // plutôt qu'un numéro de version dans l'URL de base.
        'items_path' => env('NATURABUY_ITEMS_PATH', '/v2/items'),
        'orders_path' => env('NATURABUY_ORDERS_PATH', '/v5/orders'),
    ],

    'sendcloud' => [
        'public_key' => env('SENDCLOUD_PUBLIC_KEY'),
        'secret_key' => env('SENDCLOUD_SECRET_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
     * PostHog, EU region: the data stays in Frankfurt, so no transfer outside
     * the Union has to be justified to anybody.
     *
     * Without a key nothing is loaded and no header is widened — an
     * environment that has not been given one behaves exactly as before.
     */
    'posthog' => [
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
    ],

];
