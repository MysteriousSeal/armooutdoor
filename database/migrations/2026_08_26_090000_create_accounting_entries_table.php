<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entries written by hand.
 *
 * Not everything arrives as a shop order: a prestation, a sale made across a
 * counter, a repair. They sit in the same table as the orders, at their date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table): void {
            $table->id();
            // Sales today, purchases later: the column saves doubling the
            // table when that day comes.
            $table->string('section')->default('sales');
            $table->date('entered_on');
            $table->string('invoice_number')->nullable();
            $table->string('client')->nullable();
            $table->string('channel')->nullable();
            $table->string('type')->default('stock_sale');
            $table->integer('total_cents')->default(0);
            $table->integer('fees_cents')->default(0);
            $table->string('payment_method')->default('bank_wire');
            $table->string('remark')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A month's table always reads these two columns together.
            $table->index(['section', 'entered_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
