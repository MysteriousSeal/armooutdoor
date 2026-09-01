<?php

namespace Tests\Feature\Admin;

use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Whether a customer has proved their age, on the customer page.
 *
 * The verdict is what somebody packing a restricted order needs. The document
 * itself stays on the one screen allowed to open it.
 */
class CustomerIdentityStatusTest extends TestCase
{
    use RefreshDatabase;

    private function document(User $customer, string $status): IdentityDocument
    {
        return IdentityDocument::query()->create([
            'user_id' => $customer->id,
            'kind' => 'passport',
            'original_name' => 'p.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'path' => $status === 'pending' ? 'identity-documents/x.enc' : null,
            'status' => $status,
        ]);
    }

    private function page(User $customer): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->get('/admin/customers/'.$customer->id)
            ->assertOk();
    }

    public function test_a_customer_who_sent_nothing_says_so(): void
    {
        $this->page(User::factory()->create())
            ->assertSee('No document', false)
            ->assertSee('doc-state--none', false);
    }

    public function test_a_document_waiting_shows_as_pending(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'pending');

        $this->page($customer)->assertSee('Pending verification', false)->assertSee('doc-state--pending', false);
    }

    public function test_a_reviewed_document_shows_its_verdict(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill(['reviewed_at' => now()])->save();

        $this->page($customer)->assertSee('doc-state--verified', false);
    }

    public function test_a_verdict_outranks_a_later_upload_still_waiting(): void
    {
        // Age proved once is proved; a second document waiting does not undo it.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill(['reviewed_at' => now()->subDay()])->save();
        $this->document($customer, 'pending');

        $this->assertSame('verified', $customer->identityStatus()['state']);
    }

    public function test_an_ordinary_admin_sees_the_verdict_and_no_way_in(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'pending');

        // The status, but no route to the file from this page.
        $this->page($customer)
            ->assertSee('Pending verification', false)
            ->assertDontSee(route('admin.documents.index'), false)
            ->assertDontSee('/admin/documents/', false);
    }

    public function test_the_panel_shows_the_furthest_expiry_of_all_documents(): void
    {
        // Two verifications, one running longer: the customer is covered to
        // the later of them, whatever the other says.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->addYear(),
            'reviewed_at' => now()->subMonth(),
        ])->save();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->addYears(4),
            'reviewed_at' => now(),
        ])->save();

        $this->assertTrue(
            now()->addYears(4)->isSameDay($customer->identityStatus()['until']),
        );

        $this->page($customer)
            ->assertSee('Covered until', false)
            ->assertSee(now()->addYears(4)->format('d/m/Y'), false);
    }

    public function test_a_customer_with_nothing_is_covered_until_nothing(): void
    {
        $customer = User::factory()->create();

        $this->assertNull($customer->identityStatus()['until']);
        $this->page($customer)->assertDontSee('Covered until', false);
    }

    public function test_a_lapsed_customer_is_not_told_they_are_covered(): void
    {
        // The lapse date already says when it ran out; repeating it as cover
        // would read as though something still held.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->subDay(),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->page($customer)
            ->assertSee('Expired', false)
            ->assertDontSee('Covered until', false);
    }
}
