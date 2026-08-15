<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Models\Carrier;
use App\Models\RelayPoint;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            [
                'slug' => 'colissimo-home',
                'name' => ['en' => 'Colissimo', 'fr' => 'Colissimo'],
                'description' => [
                    'en' => 'Delivered to your door by La Poste.',
                    'fr' => 'Livré chez vous par La Poste.',
                ],
                'eta' => ['en' => '2–4 days', 'fr' => '2–4 jours'],
                'method' => DeliveryMethod::Home,
                'price_cents' => 690,
                'sort_order' => 1,
            ],
            [
                'slug' => 'chronopost-home',
                'name' => ['en' => 'Chronopost', 'fr' => 'Chronopost'],
                'description' => [
                    'en' => 'Express home delivery, tracked end to end.',
                    'fr' => 'Livraison express à domicile, suivie de bout en bout.',
                ],
                'eta' => ['en' => '1–2 days', 'fr' => '1–2 jours'],
                'method' => DeliveryMethod::Home,
                'price_cents' => 1290,
                'sort_order' => 2,
            ],
            [
                'slug' => 'mondial-relay',
                'name' => ['en' => 'Mondial Relay', 'fr' => 'Mondial Relay'],
                'description' => [
                    'en' => 'Collect from a nearby shop or locker.',
                    'fr' => 'À retirer dans un commerce ou un casier proche.',
                ],
                'eta' => ['en' => '3–5 days', 'fr' => '3–5 jours'],
                'method' => DeliveryMethod::Relay,
                'price_cents' => 390,
                'sort_order' => 3,
            ],
            [
                'slug' => 'relais-pickup',
                'name' => ['en' => 'Chronopost Shop2Shop', 'fr' => 'Chronopost Shop2Shop'],
                'description' => [
                    'en' => 'Collect from a Chronopost shop or locker.',
                    'fr' => 'À retirer dans un commerce ou un casier Chronopost.',
                ],
                'eta' => ['en' => '3–4 days', 'fr' => '3–4 jours'],
                'method' => DeliveryMethod::Relay,
                'price_cents' => 450,
                'sort_order' => 4,
            ],
            [
                'slug' => 'lettre-suivie',
                'name' => ['en' => 'Lettre suivie', 'fr' => 'Lettre suivie'],
                'description' => [
                    'en' => 'Delivered to your mailbox by La Poste, tracked.',
                    'fr' => 'Livré dans votre boîte aux lettres par La Poste, suivi.',
                ],
                'eta' => ['en' => '2–3 days', 'fr' => '2–3 jours'],
                'method' => DeliveryMethod::Home,
                'price_cents' => 350,
                'sort_order' => 5,
            ],
        ];

        foreach ($carriers as $carrier) {
            Carrier::query()->updateOrCreate(
                ['slug' => $carrier['slug']],
                $carrier + ['active' => true],
            );
        }

        $points = [
            ['slug' => 'paris-archives', 'name' => 'Tabac des Archives', 'line1' => '14 rue des Archives', 'postal_code' => '75004', 'city' => 'Paris', 'hours' => 'Mon–Sat 8:00–19:30'],
            ['slug' => 'paris-lamarck', 'name' => 'Maison de la Presse Lamarck', 'line1' => '42 rue Lamarck', 'postal_code' => '75018', 'city' => 'Paris', 'hours' => 'Mon–Sat 7:30–20:00'],
            ['slug' => 'lyon-bellecour', 'name' => 'Relais Bellecour', 'line1' => '8 place Bellecour', 'postal_code' => '69002', 'city' => 'Lyon', 'hours' => 'Mon–Sat 9:00–19:00'],
            ['slug' => 'marseille-vieux-port', 'name' => 'Presse du Vieux-Port', 'line1' => '3 quai du Port', 'postal_code' => '13002', 'city' => 'Marseille', 'hours' => 'Mon–Sat 8:00–19:00'],
            ['slug' => 'toulouse-carmes', 'name' => 'Café des Carmes', 'line1' => '21 place des Carmes', 'postal_code' => '31000', 'city' => 'Toulouse', 'hours' => 'Mon–Sat 8:30–19:30'],
            ['slug' => 'lille-vieux', 'name' => 'Librairie du Vieux-Lille', 'line1' => '12 rue de la Monnaie', 'postal_code' => '59000', 'city' => 'Lille', 'hours' => 'Tue–Sat 10:00–19:00'],
            ['slug' => 'bordeaux-sainte-catherine', 'name' => 'Épicerie Sainte-Catherine', 'line1' => '67 rue Sainte-Catherine', 'postal_code' => '33000', 'city' => 'Bordeaux', 'hours' => 'Mon–Sat 9:00–19:30'],
            ['slug' => 'nantes-graslin', 'name' => 'Tabac Graslin', 'line1' => '5 place Graslin', 'postal_code' => '44000', 'city' => 'Nantes', 'hours' => 'Mon–Sat 8:00–19:00'],
            ['slug' => 'strasbourg-petite-france', 'name' => 'Relais Petite France', 'line1' => '18 rue du Bain-aux-Plantes', 'postal_code' => '67000', 'city' => 'Strasbourg', 'hours' => 'Mon–Sat 9:00–18:30'],
            ['slug' => 'rennes-halles', 'name' => 'Halles Centrales', 'line1' => 'Place des Lices', 'postal_code' => '35000', 'city' => 'Rennes', 'hours' => 'Tue–Sat 8:00–19:00'],
        ];

        foreach ($points as $index => $point) {
            RelayPoint::query()->updateOrCreate(
                ['slug' => $point['slug']],
                $point + [
                    'country' => 'FR',
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
