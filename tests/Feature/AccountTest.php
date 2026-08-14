<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_open_account_pages(): void
    {
        $this->get('/fr/account')->assertRedirect('/fr/login');
        $this->get('/fr/account/profile')->assertRedirect('/fr/login');
        $this->get('/fr/account/addresses')->assertRedirect('/fr/login');
    }

    public function test_account_hub_links_to_profile_and_addresses(): void
    {
        $user = User::factory()->create(['name' => 'Colas']);

        $this->actingAs($user)
            ->get('/fr/account')
            ->assertOk()
            ->assertSee('Bonjour, Colas')
            ->assertSee('Informations du compte')
            ->assertSee('Adresses');
    }

    public function test_a_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => 'secret-pass',
        ]);

        $this->actingAs($user)
            ->from('/fr/account/profile')
            ->put('/fr/account/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect('/fr/account/profile')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->actingAs($user)
            ->from('/fr/account/profile')
            ->put('/fr/account/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertRedirect('/fr/account/profile')
            ->assertSessionHasErrors('current_password');
    }

    public function test_a_user_can_create_and_edit_an_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/fr/account/addresses')
            ->post('/fr/account/addresses', [
                'label' => 'Home',
                'first_name' => 'Colas',
                'last_name' => 'Martin',
                'line1' => '12 rue des Archives',
                'postal_code' => '75004',
                'city' => 'Paris',
                'country' => 'FR',
                'is_default' => '1',
            ])
            ->assertRedirect('/fr/account/addresses');

        $address = Address::query()->firstOrFail();

        $this->actingAs($user)
            ->get('/fr/account/addresses/'.$address->id.'/edit')
            ->assertOk()
            ->assertSee('12 rue des Archives');

        $this->actingAs($user)
            ->put('/fr/account/addresses/'.$address->id, [
                'label' => 'Studio',
                'first_name' => 'Colas',
                'last_name' => 'Martin',
                'line1' => '8 place Bellecour',
                'postal_code' => '69002',
                'city' => 'Lyon',
                'country' => 'FR',
                'is_default' => '1',
            ])
            ->assertRedirect('/fr/account/addresses');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'city' => 'Lyon',
            'label' => 'Studio',
        ]);
    }

    public function test_a_user_cannot_edit_someone_elses_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create();

        $this->actingAs($user)
            ->get('/fr/account/addresses/'.$address->id.'/edit')
            ->assertNotFound();
    }
}
