<?php

return [
    'currency' => 'EUR',
    // La France d'abord, c'est le choix par défaut ; le reste suit l'ordre
    // alphabétique des libellés français, celui du menu déroulant.
    'countries' => ['FR', 'DE', 'BE', 'ES', 'IE', 'IT', 'LU', 'NL', 'PT', 'CH'],
    'customer_countries' => ['FR'],
    // Le chemin du back-office. Renommé en production pour ne pas s'offrir
    // au premier scanner venu ; les noms de routes (admin.*) ne bougent pas.
    'admin_path' => env('ADMIN_PATH', 'admin'),
    // The "Actions" menu on each row of the orders list. Set aside, not
    // removed: everything it offers is still written in the view, and
    // turning this flag back to true puts it back, header cell included.
    'admin_row_actions' => false,
    // The storefront's light/dark switch. Set aside, not removed: with this
    // false the header shows no switch and every storefront page renders
    // light, whatever theme a visitor picked before, so nobody is left in a
    // dark theme with no way back. Turning it back to true restores the
    // buttons and the remembered choice. The back office keeps its own.
    'theme_switch' => false,
    // Prévenu à chaque commande devenue réelle — boutique comme manuelle.
    // Vide : personne n'est prévenu.
    'order_notification_email' => env('ORDER_NOTIFICATION_EMAIL'),
    // Prévenu de ce qui attend une action : un message client, une pièce
    // d'identité à vérifier, un paiement Stripe sans commande. Retombe sur
    // l'adresse des commandes ; vide, personne n'est prévenu.
    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL', env('ORDER_NOTIFICATION_EMAIL')),
    /*
     * When each legal page was last actually changed.
     *
     * The pages used to print now(), so they claimed to have been revised on
     * whatever day they were read. A customer cannot tell from that when the
     * terms they agreed to were written, which is the one thing the line is
     * there to say. Bump the date here when the text changes, and only then.
     */
    'legal_updated' => [
        'terms' => '2026-09-01',
        'notice' => '2026-09-01',
        'privacy' => '2026-09-01',
        'withdrawal' => '2026-09-01',
    ],
    'version' => '1.39.2',
];
