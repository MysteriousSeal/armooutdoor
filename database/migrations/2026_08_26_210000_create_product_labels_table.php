<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wording of a product's printed label, in its own table.
 *
 * It was four columns on `products`, empty for most of the catalogue and about
 * a printed sheet rather than about the product itself. One row per product,
 * and only for products that have wording at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_labels', function (Blueprint $table): void {
            $table->id();
            // Unique: a product has one label, whatever its number of sizes.
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('composition', 500)->nullable();
            $table->string('mention', 500)->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('products')
            ->where(function ($query): void {
                $query->whereNotNull('label_title')
                    ->orWhereNotNull('label_subtitle')
                    ->orWhereNotNull('label_composition')
                    ->orWhereNotNull('label_mention');
            })
            ->orderBy('id')
            ->chunk(200, function ($products) use ($now): void {
                DB::table('product_labels')->insert($products->map(fn ($product): array => [
                    'product_id' => $product->id,
                    'title' => $product->label_title,
                    'subtitle' => $product->label_subtitle,
                    'composition' => $product->label_composition,
                    'mention' => $product->label_mention,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['label_title', 'label_subtitle', 'label_composition', 'label_mention']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('label_title')->nullable()->after('gtin');
            $table->string('label_subtitle')->nullable()->after('label_title');
            $table->string('label_composition', 500)->nullable()->after('label_subtitle');
            $table->string('label_mention', 500)->nullable()->after('label_composition');
        });

        DB::table('product_labels')->orderBy('id')->chunk(200, function ($labels): void {
            foreach ($labels as $label) {
                DB::table('products')->where('id', $label->product_id)->update([
                    'label_title' => $label->title,
                    'label_subtitle' => $label->subtitle,
                    'label_composition' => $label->composition,
                    'label_mention' => $label->mention,
                ]);
            }
        });

        Schema::dropIfExists('product_labels');
    }
};
