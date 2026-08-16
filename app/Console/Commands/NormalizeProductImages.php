<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\ImageThumbnailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('app:normalize-product-images')]
#[Description('Resize every product/gallery/variant image to 1000x1000 WebP, and regenerate its thumbnail')]
class NormalizeProductImages extends Command
{
    public function handle(): int
    {
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ([Product::class, ProductImage::class, ProductVariant::class] as $model) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $rows */
            $rows = $model::query()->whereNotNull('image')->where('image', '!=', '')->get();

            foreach ($rows as $row) {
                $oldPath = $row->image;

                if (str_starts_with($oldPath, 'http://') || str_starts_with($oldPath, 'https://')) {
                    $skipped++;

                    continue;
                }

                $oldThumb = ImageThumbnailer::absoluteThumbnailPath($oldPath);
                $newPath = ImageThumbnailer::normalizeMain($oldPath);

                if ($newPath === null) {
                    $failed++;
                    $this->warn('Failed: '.$oldPath);

                    continue;
                }

                if ($newPath === $oldPath) {
                    $skipped++;
                } else {
                    $row->update(['image' => $newPath]);
                    @unlink($oldThumb);
                    $converted++;
                }

                ImageThumbnailer::generate($newPath);
            }
        }

        $this->info("Converted: {$converted}, already normalized: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
