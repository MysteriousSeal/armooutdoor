<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BlogPost> */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(4, true));

        return [
            'blog_category_id' => BlogCategory::query()->firstOrCreate(
                ['slug' => 'conseils'],
                ['name' => ['fr' => 'Conseils'], 'sort_order' => 0],
            )->id,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => ['fr' => $title],
            'excerpt' => ['fr' => $this->faker->sentence()],
            'body' => ['fr' => '<p>'.$this->faker->paragraph().'</p>'],
            'status' => 'published',
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'published_at' => null]);
    }

    /** Publié, mais daté du futur. */
    public function scheduled(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now()->addWeek()]);
    }
}
