<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['conseils', 'Conseils', 'Bien choisir, bien entretenir, bien débuter.'],
            ['actualites', 'Actualités', 'Nouveautés, réassorts et vie de la boutique.'],
            ['essais', 'À l\'essai', 'Nos répliques et notre matériel pris en main.'],
            ['reglementation', 'Réglementation', 'Ce que dit la loi française sur l\'airsoft.'],
        ];

        foreach ($categories as $index => [$slug, $name, $description]) {
            BlogCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['fr' => $name],
                    'description' => ['fr' => $description],
                    'sort_order' => $index,
                ],
            );
        }
    }
}
