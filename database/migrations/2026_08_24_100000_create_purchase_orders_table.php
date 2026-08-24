<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot: the order remains readable after the supplier is
            // deleted, the same way orders snapshot their carrier.
            $table->string('supplier_name');
            // Every list view and the nav badge filter on it.
            $table->string('status')->index();
            $table->string('reference')->nullable();
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
