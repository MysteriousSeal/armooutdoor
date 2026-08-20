<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Folds each flat contact message into a conversation plus its first message.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_messages')) {
            return;
        }

        foreach (DB::table('contact_messages')->orderBy('id')->get() as $message) {
            $conversationId = DB::table('conversations')->insertGetId([
                'user_id' => $message->user_id,
                'order_id' => $message->order_id,
                'subject' => $message->subject,
                'name' => $message->name,
                'email' => $message->email,
                'status' => 'open',
                'last_customer_message_at' => $message->created_at,
                'last_admin_message_at' => null,
                'admin_last_read_at' => $message->read_at,
                'customer_last_read_at' => null,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]);

            DB::table('conversation_messages')->insert([
                'conversation_id' => $conversationId,
                'user_id' => $message->user_id,
                'author_type' => 'customer',
                'body' => $message->message,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]);
        }

        Schema::drop('contact_messages');
    }

    /**
     * Rebuilds contact_messages from the first message of each conversation.
     * Any later replies are lost — the old flat shape has nowhere to put them.
     */
    public function down(): void
    {
        if (Schema::hasTable('contact_messages')) {
            return;
        }

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('conversations')->orderBy('id')->get() as $conversation) {
            $first = DB::table('conversation_messages')
                ->where('conversation_id', $conversation->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            DB::table('contact_messages')->insert([
                'user_id' => $conversation->user_id,
                'order_id' => $conversation->order_id,
                'name' => $conversation->name,
                'email' => $conversation->email,
                'subject' => $conversation->subject,
                'message' => $first->body ?? '',
                'read_at' => $conversation->admin_last_read_at,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ]);
        }
    }
};
