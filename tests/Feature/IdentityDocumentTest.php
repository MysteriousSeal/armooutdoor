<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proof of age: encrypted on arrival, readable by one role, gone once read.
 */
class IdentityDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, string $contents = 'PASSPORT BYTES'): IdentityDocument
    {
        $this->actingAs($user)->post('/account/documents', [
            'kind' => 'passport',
            'document' => UploadedFile::fake()->createWithContent('passport.pdf', $contents),
        ])->assertRedirect();

        return $user->identityDocuments()->latest()->firstOrFail();
    }

    public function test_the_file_on_disk_is_not_the_file_that_was_sent(): void
    {
        $document = $this->upload(User::factory()->create(), 'PASSPORT BYTES');

        $stored = Storage::disk(IdentityDocument::DISK)->get($document->path);

        $this->assertStringNotContainsString('PASSPORT BYTES', $stored);
        $this->assertSame('PASSPORT BYTES', $document->decrypted());
    }

    public function test_it_is_written_outside_anything_the_web_server_serves(): void
    {
        $document = $this->upload(User::factory()->create());

        $this->assertStringStartsWith('identity-documents/', $document->path);
        $this->assertStringNotContainsString(
            'public',
            config('filesystems.disks.'.IdentityDocument::DISK.'.root'),
        );
    }

    public function test_a_customer_cannot_reach_another_customer_document(): void
    {
        $document = $this->upload(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete('/account/documents/'.$document->id)
            ->assertForbidden();

        $this->assertDatabaseHas('identity_documents', ['id' => $document->id]);
    }

    public function test_an_ordinary_admin_cannot_open_one(): void
    {
        $document = $this->upload(User::factory()->create());
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->get('/admin/documents/'.$document->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/documents')->assertForbidden();
    }

    public function test_a_guest_cannot_reach_the_page_at_all(): void
    {
        $this->get('/account/documents')->assertRedirect();
    }

    public function test_reviewing_records_the_verdict_and_destroys_the_file(): void
    {
        $document = $this->upload(User::factory()->create());
        $owner = User::factory()->create(['role' => 'owner', 'is_admin' => true]);
        $path = $document->path;

        $this->actingAs($owner)
            ->patch('/admin/documents/'.$document->id, [
                'status' => 'verified',
                // Required since the verification carries the document's own
                // expiry date.
                'expires_at' => now()->addYears(5)->toDateString(),
                'review_note' => 'Née en 1994',
            ])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame('verified', $document->status);
        $this->assertNotNull($document->reviewed_at);
        $this->assertSame($owner->id, $document->reviewed_by_user_id);
        $this->assertTrue($document->expires_at->isFuture());
        // The verdict survives; the passport does not.
        $this->assertNull($document->path);
        $this->assertFalse(Storage::disk(IdentityDocument::DISK)->exists($path));
    }

    public function test_opening_one_is_written_to_the_activity_log(): void
    {
        $document = $this->upload(User::factory()->create());
        $owner = User::factory()->create(['role' => 'owner', 'is_admin' => true]);

        $this->actingAs($owner)->get('/admin/documents/'.$document->id)->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $owner->id,
            'action' => 'identity_document.viewed',
        ]);
    }

    public function test_a_customer_can_withdraw_their_own(): void
    {
        $user = User::factory()->create();
        $document = $this->upload($user);
        $path = $document->path;

        $this->actingAs($user)->delete('/account/documents/'.$document->id)->assertRedirect();

        $this->assertDatabaseMissing('identity_documents', ['id' => $document->id]);
        $this->assertFalse(Storage::disk(IdentityDocument::DISK)->exists($path));
    }

    public function test_an_executable_upload_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/account/documents', [
                'kind' => 'passport',
                'document' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('identity_documents', 0);
    }

    public function test_the_page_asks_not_to_be_indexed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account/documents')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_customer_sees_how_long_their_proof_lasts(): void
    {
        $user = User::factory()->create();
        $this->upload($user)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->addYears(3),
            'reviewed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get('/account/documents')
            ->assertOk()
            ->assertSee('Valide jusqu\'au '.now()->addYears(3)->translatedFormat('d F Y'))
            ->assertSee('doc-status--verified', false);
    }

    public function test_a_lapsed_proof_says_so_and_says_what_to_do(): void
    {
        // The status has to follow the date: telling a customer they are
        // verified when their document expired last month is the one answer
        // that is worse than saying nothing.
        $user = User::factory()->create();
        $this->upload($user)->forceFill([
            'status' => 'verified',
            'expires_at' => now()->subMonth(),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->actingAs($user)
            ->get('/account/documents')
            ->assertOk()
            ->assertSee('doc-status--expired', false)
            ->assertSee('A expiré le '.now()->subMonth()->translatedFormat('d F Y'))
            ->assertSee(__('store.documents_expired_hint'))
            ->assertDontSee('doc-status--verified', false);
    }

    public function test_a_document_still_waiting_shows_no_validity(): void
    {
        $user = User::factory()->create();
        $this->upload($user);

        $this->actingAs($user)
            ->get('/account/documents')
            ->assertOk()
            ->assertDontSee('doc-item-validity', false);
    }
}
