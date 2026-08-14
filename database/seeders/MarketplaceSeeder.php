<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['NaturaBuy', 'CDiscount', 'Ebay', 'LeBonCoin', 'Vinted'] as $name) {
            Marketplace::query()->firstOrCreate(['name' => $name]);
        }
    }
}
