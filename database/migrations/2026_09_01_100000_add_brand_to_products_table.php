<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The maker of a product, as a field of its own.
     *
     * It was recorded as a « Marque » characteristic, which is free-form text
     * in a list meant for describing an article, and only 38 products carried
     * one. Search engines read the brand back against merchant feeds, so it
     * earns a column.
     *
     * The existing entries are copied across and left where they are: the
     * category filters are built generically from `filter_attributes`, so
     * removing them would take the brand filter down with them.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand', 80)->nullable()->after('meta_description');
        });

        foreach (DB::table('products')->select('id', 'characteristics', 'filter_attributes')->get() as $row) {
            $brand = $this->brandIn($row->characteristics) ?? $this->brandIn($row->filter_attributes);

            if ($brand !== null) {
                DB::table('products')->where('id', $row->id)->update(['brand' => $brand]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    private function brandIn(?string $json): ?string
    {
        foreach (json_decode((string) $json, true) ?: [] as $entry) {
            if (($entry['label'] ?? '') === 'Marque') {
                $value = trim((string) ($entry['value'] ?? ''));

                if ($value !== '') {
                    return mb_substr($value, 0, 80);
                }
            }
        }

        return null;
    }
};
