<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/settings/admins')
            ->assertOk()
            ->assertSee($otherAdmin->email)
            ->assertDontSee($customer->email);
    }

    public function test_admin_can_create_a_new_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/settings/admins', [
            'first_name' => 'New',
            'last_name' => 'Admin',
            'email' => 'newadmin@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect('/admin/settings/admins');

        $created = User::query()->where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertTrue($created->is_admin);
        $this->assertTrue(Hash::check('secret123', $created->password));

        // The new admin can actually sign in.
        $this->post('/admin/login', [
            'email' => 'newadmin@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_creating_an_admin_requires_matching_password_confirmation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/settings/admins/create')
            ->post('/admin/settings/admins', [
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => 'mismatch@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect('/admin/settings/admins/create')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_creating_an_admin_requires_a_unique_email(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->from('/admin/settings/admins/create')
            ->post('/admin/settings/admins', [
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => $existing->email,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_another_admins_name_and_email_without_touching_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create(['password' => 'original-pass']);
        $originalHash = $target->password;

        $this->actingAs($admin)
            ->put('/admin/settings/admins/'.$target->id, [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect('/admin/settings/admins');

        $target->refresh();
        $this->assertSame('Updated', $target->first_name);
        $this->assertSame('updated@example.com', $target->email);
        $this->assertSame($originalHash, $target->password);
    }

    public function test_admin_can_reset_another_admins_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/admins/'.$target->id, [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $this->assertTrue(Hash::check('brand-new-pass', $target->fresh()->password));
    }

    public function test_editing_a_non_admin_user_404s(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/settings/admins/'.$customer->id.'/edit')
            ->assertNotFound();

        $this->actingAs($admin)
            ->put('/admin/settings/admins/'.$customer->id, [
                'first_name' => 'X',
                'last_name' => 'Y',
                'email' => 'x@example.com',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_deactivate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$target->id.'/deactivate')
            ->assertRedirect('/admin/settings/admins');

        $target->refresh();
        $this->assertFalse($target->is_admin);
        $this->assertNotNull($target->admin_deactivated_at);
    }

    public function test_deactivated_admins_appear_in_the_deactivated_tab(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings/admins/'.$target->id.'/deactivate');

        $this->actingAs($admin)
            ->get('/admin/settings/admins?tab=deactivated')
            ->assertOk()
            ->assertSee($target->email);

        $this->actingAs($admin)
            ->get('/admin/settings/admins')
            ->assertOk()
            ->assertDontSee($target->email);
    }

    public function test_admin_can_reactivate_a_deactivated_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $this->actingAs($admin)->patch('/admin/settings/admins/'.$target->id.'/deactivate');

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$target->id.'/reactivate')
            ->assertRedirect('/admin/settings/admins');

        $target->refresh();
        $this->assertTrue($target->is_admin);
        $this->assertNull($target->admin_deactivated_at);
    }

    public function test_reactivating_a_currently_active_admin_404s(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$target->id.'/reactivate')
            ->assertNotFound();
    }

    public function test_reactivating_a_user_who_was_never_an_admin_404s(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$customer->id.'/reactivate')
            ->assertNotFound();
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // keep at least 2 admins so the "last admin" guard isn't the blocker

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$admin->id.'/deactivate')
            ->assertSessionHas('status');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_deactivating_a_non_admin_user_404s(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings/admins/'.$customer->id.'/deactivate')
            ->assertNotFound();
    }
}
