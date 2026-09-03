<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\AdminActivityLog;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * One click on the GMC column: in the Google feed, or out of it. A
     * parent taken out takes its subcategories' products with it — the
     * switch exists for Google's policy refusals, and those come by aisle.
     */
    public function toggleGoogleFeed(\Illuminate\Http\Request $request, Category $category): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $category->update(['google_feed' => ! $category->google_feed]);

        AdminActivityLog::record(
            'category.google_feed',
            null,
            ($category->google_feed ? 'Included ' : 'Excluded ').($category->name['fr'] ?? $category->slug).($category->google_feed ? ' in' : ' from').' the Google feed',
        );

        // The pill flips in place; a browser without the script falls back
        // to the ordinary redirect, same as the supplier toggle.
        if ($request->expectsJson()) {
            return response()->json(['google_feed' => $category->google_feed]);
        }

        return back();
    }

    public function index(): View
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->withCount('products')])
            ->withCount(['products', 'children'])
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
        $category = Category::query()->create($this->payload($request));

        if ($request->hasFile('image')) {
            $category->update(['image' => $this->storeHeroImage($request->file('image'))]);
        }

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

        if ($request->hasFile('image')) {
            $this->deleteStoredImageFile($category->image);
            $category->update(['image' => $this->storeHeroImage($request->file('image'))]);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteStoredImageFile($category->image);
            $category->update(['image' => null]);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return redirect()
                ->route('admin.categories.index')
                ->with('status', 'That category still has products or subcategories — it can\'t be removed.');
        }

        $name = $category->localizedName();
        $this->deleteStoredImageFile($category->image);
        $category->delete();
        AdminActivityLog::record('category.deleted', null, 'Removed category '.$name);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category removed.');
    }

    /**
     * Resizes and center-crops to a 1890×810 (21:9) WebP hero banner.
     */
    private function storeHeroImage(UploadedFile $file): string
    {
        $directory = public_path('images/categories');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $targetWidth = 1890;
        $targetHeight = 810;
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $cropWidth = (int) round($targetWidth / $scale);
        $cropHeight = (int) round($targetHeight / $scale);
        $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);

        $name = 'category-'.Str::lower(Str::random(8)).'.webp';
        imagewebp($target, $directory.'/'.$name, 85);
        imagedestroy($source);
        imagedestroy($target);

        return 'categories/'.$name;
    }

    private function deleteStoredImageFile(?string $image): void
    {
        if ($image === null || $image === '' || str_starts_with($image, 'http')) {
            return;
        }

        $path = public_path('images/'.$image);

        if (is_file($path)) {
            @unlink($path);
        }
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
