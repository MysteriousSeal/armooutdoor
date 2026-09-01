<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * A proof of age, held for as long as it takes to read it and no longer.
 */
#[Fillable(['user_id', 'kind', 'original_name', 'mime', 'size_bytes', 'path', 'status'])]
class IdentityDocument extends Model
{
    public const KINDS = ['id_card', 'passport', 'driving_licence'];

    /** The disk lives outside the document root; nothing here is ever a URL. */
    public const DISK = 'local';

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'expires_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Past the date on the document itself.
     *
     * Compared when asked rather than written down by a nightly job: a proof
     * that lapsed at midnight has lapsed, whether or not anything ran.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * What this one document says today: verified, expired, or its own status.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === 'verified' && $this->hasExpired()) {
            return 'expired';
        }

        return $this->status;
    }

    public function fileExists(): bool
    {
        return $this->path !== null && Storage::disk(self::DISK)->exists($this->path);
    }

    /**
     * The document, decrypted, for the one screen allowed to show it.
     *
     * Bytes rather than a path or a stream: nothing else in the application
     * should be able to reach the plaintext, and a caller holding a string is
     * a caller that has to be handed it deliberately.
     */
    public function decrypted(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return Crypt::decryptString(Storage::disk(self::DISK)->get($this->path));
    }

    /**
     * Removes the file and leaves the row.
     *
     * Called when a document is reviewed, replaced or withdrawn. The record of
     * what was decided survives; the passport does not.
     */
    public function forgetFile(): void
    {
        if ($this->path !== null) {
            Storage::disk(self::DISK)->delete($this->path);
        }

        $this->forceFill(['path' => null])->save();
    }
}
