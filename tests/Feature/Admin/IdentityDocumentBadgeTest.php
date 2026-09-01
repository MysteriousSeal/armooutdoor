<?php

namespace Tests\Feature\Admin;

use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Documents waiting to be looked at, counted in the menu.
 *
 * A document nobody opens is a customer who cannot order a restricted item,
 * and nothing else on the screen says so.
 */
class IdentityDocumentBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $status): IdentityDocument
    {
        return IdentityDocument::query()->create([
            'user_id' => User::factory()->create()->id,
            'kind' => 'passport',
            'original_name' => 'p.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'path' => $status === 'pending' ? 'identity-documents/x.enc' : null,
            'status' => $status,
        ]);
    }

    public function test_the_menu_counts_what_is_waiting(): void
    {
        $this->document('pending');
        $this->document('pending');
        $this->document('verified');

        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('2 awaiting review', false);
    }

    public function test_nothing_waiting_shows_no_badge(): void
    {
        $this->document('verified');

        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('awaiting review', false);
    }

    public function test_an_ordinary_admin_is_not_told_about_work_they_cannot_do(): void
    {
        // The screen is owner-only, so a badge would point an admin at a door
        // that answers 403.
        $this->document('pending');

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('awaiting review', false)
            ->assertDontSee('Identity documents', false);
    }
}
