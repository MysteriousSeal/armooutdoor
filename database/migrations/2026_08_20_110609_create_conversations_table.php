<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');

            // Snapshotted at creation so a thread still reads correctly if the
            // account is later renamed, or was never an account at all (guest).
            $table->string('name');
            $table->string('email');

            $table->string('status')->default('open');

            // Denormalised so unread state is a column comparison rather than a
            // correlated subquery: the admin nav badge runs on every admin page.
            // Only ever written by Conversation::postMessage().
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_admin_message_at')->nullable();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamp('customer_last_read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
