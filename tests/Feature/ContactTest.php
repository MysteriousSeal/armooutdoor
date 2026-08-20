<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_is_public(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Nous contacter');
    }

    public function test_a_guest_can_send_a_message(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, où en est ma commande ?',
        ]);

        $response->assertRedirect('/contact');
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question sur une commande',
            'user_id' => null,
        ]);
    }

    public function test_a_logged_in_customer_message_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/contact', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Retour produit',
            'message' => 'Comment retourner un article ?',
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }

    public function test_message_is_required(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => '',
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_filled_honeypot_field_rejects_the_submission(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'spam',
            'message' => 'spam',
            'website' => 'https://spam.example.com',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_non_admins_cannot_view_the_admin_message_inbox(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin/messages')
            ->assertRedirect('/admin');
    }

    public function test_admin_can_view_and_read_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::query()->create([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour !',
        ]);

        $this->actingAs($admin)
            ->get('/admin/messages')
            ->assertOk()
            ->assertSee('Jean Martin');

        $this->assertNull($message->fresh()->read_at);

        $this->actingAs($admin)
            ->get('/admin/messages/'.$message->id)
            ->assertOk()
            ->assertSee('Bonjour !');

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_admin_nav_shows_unread_message_count(): void
    {
        $admin = User::factory()->admin()->create();
        ContactMessage::query()->create([
            'name' => 'Jean Martin',
            'email' => 'jean@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour !',
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-nav-badge', false);
    }
}
