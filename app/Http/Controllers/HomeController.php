<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\HomepageCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $coverSlugs = [
            'targets' => '200-round-targets-fluorescent-splatter-targets-for-precision-shooting-practice',
            'range' => 'black-100-round-9mm-ammunition-storage-box-with-secure-latch-organizer-case',
            'apparel' => 'cp-camouflage-unisex-outdoor-snapback-cap-moisture-wicking-quick-dry-breathable-hat',
            'field-gear' => 'magnesium-fire-starter-with-wooden-handle-outdoor-survival-flint-tool',
            'everyday' => 'bracelet-apple-watch-noir-paracorde-nylon-homme-42-44-45-46-49mm',
        ];

        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active()->orderBy('sort_order'),
                'children.products' => fn ($query) => $query->active()->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->each(function (Category $category) use ($coverSlugs): void {
                $preferred = $coverSlugs[$category->slug] ?? null;
                $pool = $category->products->concat(
                    $category->children->flatMap(fn (Category $child) => $child->products),
                );

                $category->setRelation(
                    'coverProduct',
                    $pool->firstWhere('slug', $preferred) ?? $pool->first(),
                );
            });

        $featured = HomepageCatalog::featured();
        $more = HomepageCatalog::more($featured);

        return view('home', compact('categories', 'featured', 'more'));
    }
}
