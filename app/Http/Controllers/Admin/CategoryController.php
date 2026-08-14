<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->withCount('products')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['sort_order' => 0]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($this->payload($request));

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->payload($request, $category));

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreCategoryRequest $request, ?Category $category = null): array
    {
        $slug = $request->input('slug') ?: Str::slug($request->string('name'));

        if ($slug === '') {
            $slug = 'category-'.Str::lower(Str::random(6));
        }

        $original = $slug;
        $i = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->where('id', '!=', $category->id))
            ->exists()) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return [
            'parent_id' => $request->integer('parent_id') ?: null,
            'slug' => $slug,
            'name' => [
                'fr' => $request->string('name')->toString(),
            ],
            'description' => [
                'fr' => $request->string('description')->toString(),
            ],
            'sort_order' => $request->integer('sort_order'),
        ];
    }

    /**
     * Only top-level categories can be a parent — subcategories can't nest further.
     */
    private function parentOptions(?Category $category = null): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->when($category, fn ($query) => $query->where('id', '!=', $category->id))
            ->orderBy('sort_order')
            ->get();
    }
}
