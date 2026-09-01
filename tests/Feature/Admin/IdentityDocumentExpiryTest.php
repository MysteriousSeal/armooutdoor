<?php

namespace Tests\Feature\Admin;

use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A verification lasts as long as the document it was read from.
 *
 * The date is required to verify and meaningless to reject, and it is compared
 * when asked rather than written down by a nightly job — a proof that lapsed
 * at midnight has lapsed, whether or not anything ran.
 */
class IdentityDocumentExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'is_admin' => true]);
    }

    private function pending(User $customer): IdentityDocument
    {
        return IdentityDocument::query()->create([
            'user_id' => $customer->id,
            'kind' => 'passport',
            'original_name' => 'p.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'path' => null,
            'status' => 'pending',
        ]);
    }

    public function test_verifying_without_a_date_is_refused(): void
    {
        $document = $this->pending(User::factory()->create());

        $this->actingAs($this->owner())
            ->patch('/admin/documents/'.$document->id, ['status' => 'verified'])
            ->assertSessionHasErrors('expires_at');

        $this->assertSame('pending', $document->fresh()->status);
    }

    public function test_a_date_already_past_is_refused(): void
    {
        $document = $this->pending(User::factory()->create());

        $this->actingAs($this->owner())
            ->patch('/admin/documents/'.$document->id, [
                'status' => 'verified',
                'expires_at' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('expires_at');
    }

    public function test_rejecting_needs_no_date_at_all(): void
    {
        // A refused document has no validity to run out.
        $document = $this->pending(User::factory()->create());

        $this->actingAs($this->owner())
            ->patch('/admin/documents/'.$document->id, ['status' => 'rejected'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $document->fresh()->status);
        $this->assertNull($document->fresh()->expires_at);
    }

    public function test_a_verified_document_stops_counting_the_day_it_lapses(): void
    {
        $customer = User::factory()->create();
        $document = $this->pending($customer);

        $this->actingAs($this->owner())->patch('/admin/documents/'.$document->id, [
            'status' => 'verified',
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('verified', $customer->identityStatus()['state']);

        // Nothing runs; the date simply passes.
        $this->travel(400)->days();

        $this->assertSame('expired', $customer->fresh()->identityStatus()['state']);
        $this->assertTrue($document->fresh()->hasExpired());
    }

    public function test_a_lapse_ranks_below_a_new_document_waiting(): void
    {
        $customer = User::factory()->create();
        $this->pending($customer)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->subDay(),
            'reviewed_at' => now()->subYear(),
        ])->save();
        $this->pending($customer);

        // Something is being looked at, so that is the answer, not the lapse.
        $this->assertSame('pending', $customer->identityStatus()['state']);
    }

    public function test_a_lapse_still_outranks_a_refusal(): void
    {
        // Having complied once and run out is not the same as being refused.
        $customer = User::factory()->create();
        $this->pending($customer)->forceFill(['status' => 'rejected', 'reviewed_at' => now()])->save();
        $this->pending($customer)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->subDay(),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->assertSame('expired', $customer->identityStatus()['state']);
    }

    public function test_the_customer_page_names_the_lapse_and_its_date(): void
    {
        $customer = User::factory()->create();
        $this->pending($customer)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->subDays(3),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->get('/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('Expired', false)
            ->assertSee('doc-state--expired', false)
            ->assertSee('Lapsed on '.now()->subDays(3)->format('d/m/Y'), false);
    }

    public function test_the_review_controls_are_sized_rather_than_stretched(): void
    {
        // The date input had no height of its own, so it stretched to the
        // tallest cell in the row and pulled the whole table open with it.
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString('.doc-admin-date', $css);
        $this->assertStringContainsString('.admin-documents-table', $css);
        // The class that caused it is gone, not merely overridden.
        $this->assertStringNotContainsString('doc-admin-expiry-field', $css);
        $this->assertStringNotContainsString(
            'doc-admin-expiry-field',
            file_get_contents(resource_path('views/admin/documents/index.blade.php')),
        );
    }

    public function test_the_date_field_is_still_labelled_for_a_screen_reader(): void
    {
        // Its visible label went when the column did; the input has to say
        // what it is some other way.
        $customer = User::factory()->create();
        $this->pending($customer);

        $this->actingAs($this->owner())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('aria-label="Valid until"', false);
    }

    public function test_the_expiry_has_a_column_of_its_own(): void
    {
        $customer = User::factory()->create();
        $this->pending($customer)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->addYear(),
            'reviewed_at' => now(),
        ])->save();

        $this->actingAs($this->owner())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('<th>Valid until</th>', false)
            ->assertSee('class="doc-admin-valid"', false)
            ->assertSee(now()->addYear()->format('d/m/Y'), false);
    }

    public function test_a_lapsed_date_is_marked_in_its_own_column(): void
    {
        $customer = User::factory()->create();
        $this->pending($customer)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->subDay(),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->actingAs($this->owner())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('doc-admin-valid is-lapsed', false)
            ->assertSee('Lapsed', false);
    }

    public function test_a_document_nobody_has_reviewed_shows_no_date(): void
    {
        // The date is read off the document when it is verified, so there is
        // nothing to show before that.
        $this->pending(User::factory()->create());

        $this->actingAs($this->owner())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('—', false);
    }
}
