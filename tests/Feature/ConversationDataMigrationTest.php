<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that folded flat contact messages into conversations dropped
 * its source table, so it can only ever run once against real data. These
 * tests pin the shape it left behind.
 */
class ConversationDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_contact_messages_table_is_gone(): void
    {
        $this->assertFalse(Schema::hasTable('contact_messages'));
        $this->assertTrue(Schema::hasTable('conversations'));
        $this->assertTrue(Schema::hasTable('conversation_messages'));
    }

    public function test_deleting_a_conversation_deletes_its_messages(): void
    {
        $conversationId = DB::table('conversations')->insertGetId([
            'subject' => 'Question',
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversation_messages')->insert([
            'conversation_id' => $conversationId,
            'author_type' => 'customer',
            'body' => 'Bonjour',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversations')->where('id', $conversationId)->delete();

        $this->assertDatabaseCount('conversation_messages', 0);
    }
}
