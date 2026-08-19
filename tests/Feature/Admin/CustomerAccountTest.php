<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['is_admin' => false, 'external' => false]);
    }

    public function test_admin_can_update_customer_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();

        $response = $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'customer.updated',
            'user_id' => $admin->id,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->customer();
        $customer = $this->customer();

        $response = $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $existing->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_edit_another_admin_via_customer_route(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.customers.update', $otherAdmin), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'x@example.com',
        ]);

        $response->assertNotFound();
    }

    public function test_admin_can_send_password_reset_link(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $customer = $this->customer();

        $response = $this->actingAs($admin)->post(route('admin.customers.send-reset-link', $customer));

        $response->assertRedirect();
        Notification::assertSentTo($customer, ResetPassword::class);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'customer.password_reset_sent',
            'user_id' => $admin->id,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_activity_log_labels_customer_subject_as_customer_not_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.customers.send-reset-link', $customer));

        $log = AdminActivityLog::query()->where('action', 'customer.password_reset_sent')->firstOrFail();
        $this->assertSame('Customer', $log->subjectLabel());
    }
}
