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
    'version' => '0.34.1',
];
