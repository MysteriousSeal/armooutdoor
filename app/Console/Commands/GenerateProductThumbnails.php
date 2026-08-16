<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\ImageThumbnailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-product-thumbnails {--force : Regenerate even if a thumbnail already exists}')]
#[Description('Generate 400x400 WebP thumbnails for every product, gallery, and variant image')]
class GenerateProductThumbnails extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        $paths = Product::query()->pluck('image')
            ->concat(ProductImage::query()->pluck('image'))
            ->concat(ProductVariant::query()->whereNotNull('image')->pluck('image'))
            ->filter()
            ->unique()
            ->values();

        $this->info('Found '.$paths->count().' distinct image paths.');

        foreach ($paths as $path) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                $skipped++;

                continue;
            }

            if (! $force && is_file(ImageThumbnailer::absoluteThumbnailPath($path))) {
                $skipped++;

                continue;
            }

            if (ImageThumbnailer::generate($path)) {
                $generated++;
            } else {
                $failed++;
                $this->warn('Failed: '.$path);
            }
        }

        $this->info("Generated: {$generated}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
