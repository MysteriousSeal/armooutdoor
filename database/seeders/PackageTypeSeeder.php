<?php

namespace Database\Seeders;

use App\Models\PackageType;
use Illuminate\Database\Seeder;

class PackageTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Boîte en carton', 'Sachet d\'expédition plastique', 'Enveloppe à bulle'] as $name) {
            PackageType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
