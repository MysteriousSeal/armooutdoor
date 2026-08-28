<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The email test bench: the owner points it at an address and a branded
 * test email goes out; everyone else doesn't even see the door.
 */
class AdminEmailTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_send_a_test_email(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings/email/test', ['email' => 'inbox@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('inbox@example.com'));
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'settings.test_email_sent']);
    }

    public function test_an_invalid_address_is_refused(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings/email/test', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_staff_admins_are_kept_out(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        $this->actingAs($staff)->get('/admin/settings/email')->assertForbidden();
        $this->actingAs($staff)->post('/admin/settings/email/test', ['email' => 'inbox@example.com'])->assertForbidden();
    }

    public function test_the_page_shows_the_transport_diagnostics(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'contact@armooutdoor.fr']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/email')
            ->assertOk()
            ->assertSee('Transport')
            ->assertSee('contact@armooutdoor.fr')
            ->assertSee('storage/logs', false)
            ->assertSee('From address');
    }
}
