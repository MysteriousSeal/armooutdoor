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
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account/profile')->assertRedirect('/login');
        $this->get('/account/addresses')->assertRedirect('/login');
    }

    public function test_account_hub_links_to_profile_and_addresses(): void
    {
        $user = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Martin']);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Bonjour, Jean MARTIN')
            ->assertSee('Informations du compte')
            ->assertSee('Adresses');
    }

    public function test_a_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
            'password' => 'secret-pass',
        ]);

        $this->actingAs($user)
            ->from('/account/profile')
            ->put('/account/profile', [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect('/account/profile')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->actingAs($user)
            ->from('/account/profile')
            ->put('/account/profile', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertRedirect('/account/profile')
            ->assertSessionHasErrors('current_password');
    }

    public function test_a_user_can_create_and_edit_an_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/account/addresses')
            ->post('/account/addresses', [
                'label' => 'Home',
                'first_name' => 'Jean',
                'last_name' => 'Martin',
                'line1' => '12 rue des Archives',
                'postal_code' => '75004',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '0611223344',
                'is_default' => '1',
            ])
            ->assertRedirect('/account/addresses');

        $address = Address::query()->firstOrFail();

        $this->actingAs($user)
            ->get('/account/addresses/'.$address->id.'/edit')
            ->assertOk()
            ->assertSee('12 rue des Archives');

        $this->actingAs($user)
            ->put('/account/addresses/'.$address->id, [
                'label' => 'Studio',
                'first_name' => 'Jean',
                'last_name' => 'Martin',
                'line1' => '8 place Bellecour',
                'postal_code' => '69002',
                'city' => 'Lyon',
                'country' => 'FR',
                'phone' => '0611223344',
                'is_default' => '1',
            ])
            ->assertRedirect('/account/addresses');

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
            ->get('/account/addresses/'.$address->id.'/edit')
            ->assertNotFound();
    }
}
