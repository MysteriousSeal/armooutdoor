<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use App\Support\ImageThumbnailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    private const LOGO_SIZE = 100;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:marketplaces,name'],
            'note' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $this->storeLogo($request->file('logo'), $validated['name']);
        }

        Marketplace::query()->create($validated);

        return redirect()
            ->route('admin.settings.orders.edit')
            ->with('status', 'Marketplace added.');
    }

    public function update(Request $request, Marketplace $marketplace): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('logo')) {
            $this->deleteLogo($marketplace->logo);
            $validated['logo'] = $this->storeLogo($request->file('logo'), $marketplace->name);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($marketplace->logo);
            $validated['logo'] = null;
        }

        unset($validated['remove_logo']);

        $marketplace->update($validated);

        return redirect()
            ->route('admin.settings.orders.edit')
            ->with('status', 'Marketplace updated.');
    }

    public function destroy(Marketplace $marketplace): RedirectResponse
    {
        $this->deleteLogo($marketplace->logo);
        $marketplace->delete();

        return redirect()
            ->route('admin.settings.orders.edit')
            ->with('status', 'Marketplace removed.');
    }

    private function storeLogo(UploadedFile $file, string $name): string
    {
        $directory = public_path('images/marketplaces');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::slug($name).'-'.Str::lower(Str::random(6)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        $relativePath = 'marketplaces/'.$filename;

        return ImageThumbnailer::normalizeSquare($relativePath, self::LOGO_SIZE) ?? $relativePath;
    }

    private function deleteLogo(?string $logo): void
    {
        if ($logo === null || $logo === '') {
            return;
        }

        $path = public_path('images/'.$logo);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
