<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\Admin\UpdateCategoryRequest;
use App\Models\AdminActivityLog;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->select('id', 'category_id', 'name')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->localizedName(),
                'parent_id' => $category->parent_id,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->localizedName(),
                ]),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($this->payload($request));

        return response()->json(['data' => $this->serialize($category)], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($this->payload($request, $category));

        return response()->json(['data' => $this->serialize($category->refresh())]);
    }

    public function destroy(Category $category): JsonResponse
    {
        // Même règle qu'au back-office : une catégorie encore habitée ne se
        // supprime pas — l'API ne doit pas orpheliner des produits.
        if ($category->products()->exists() || $category->children()->exists()) {
            return response()->json([
                'message' => 'That category still has products or subcategories — it can\'t be removed.',
            ], 422);
        }

        $name = $category->localizedName();
        $category->delete();
        AdminActivityLog::record('category.deleted', null, 'Removed category '.$name.' (API)');

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function serialize(Category $category): array
    {
        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->localizedName(),
            'description' => $category->localizedDescription(),
            'parent_id' => $category->parent_id,
            'sort_order' => $category->sort_order,
            'image' => $category->image,
        ];
    }

    /**
     * Même façonnage qu'au back-office : slug déduit du nom si absent,
     * suffixé jusqu'à être unique ; nom et description rangés sous 'fr'.
     *
     * @param  StoreCategoryRequest|UpdateCategoryRequest  $request
     * @return array<string, mixed>
     */
    private function payload($request, ?Category $category = null): array
    {
        $payload = [];

        if ($request->has('name')) {
            $payload['name'] = ['fr' => $request->string('name')->toString()];
        }

        if ($request->has('description')) {
            $payload['description'] = ['fr' => $request->string('description')->toString()];
        }

        if ($request->has('parent_id')) {
            $payload['parent_id'] = $request->integer('parent_id') ?: null;
        }

        if ($request->has('sort_order')) {
            $payload['sort_order'] = $request->integer('sort_order');
        }

        if ($request->has('image')) {
            $payload['image'] = $request->string('image')->toString() ?: null;
        }

        $needsSlug = $category === null || $request->filled('slug');

        if ($needsSlug) {
            $slug = $request->string('slug')->toString() ?: Str::slug($request->string('name'));

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

            $payload['slug'] = $slug;
        }

        if ($category === null) {
            $payload['sort_order'] ??= 0;
        }

        return $payload;
    }
}
