<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One accounting journal, taken out of the admin at a given moment.
 *
 * The rows are only ever read back as "when was this month last taken, and by
 * whom", but each download is kept: a book that leaves the building is worth
 * a trail, not a single overwritten date.
 */
#[Fillable(['section', 'month', 'fingerprint', 'user_id'])]
class AccountingJournalDownload extends Model
{
    /** The admin who downloaded it. Null once that account is deleted. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Narrows to one side of the accounts, sales or purchases. */
    public function scopeSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    /**
     * Writes down that a journal has just been taken.
     *
     * The fingerprint is what the sheet said at that moment, so a later visit
     * can tell whether the filed copy still matches the month.
     */
    public static function record(string $section, string $month, string $fingerprint, ?int $userId): self
    {
        return static::query()->create([
            'section' => $section,
            'month' => $month,
            'fingerprint' => $fingerprint,
            'user_id' => $userId,
        ]);
    }

    /**
     * Whether the month has moved since this copy was taken.
     *
     * A download from before fingerprints were kept answers false: nothing is
     * known about it, and crying wolf over every old month would teach the
     * warning to be ignored.
     */
    public function isStaleAgainst(string $fingerprint): bool
    {
        return $this->fingerprint !== null && $this->fingerprint !== $fingerprint;
    }

    /** The most recent download of one month, or null if it never left. */
    public static function latestFor(string $section, string $month): ?self
    {
        return static::query()
            ->section($section)
            ->where('month', $month)
            ->with('user')
            ->latest()
            ->latest('id')
            ->first();
    }

    /**
     * The most recent download of every month of a section, keyed by month.
     *
     * One query for the whole list, rather than one per card.
     *
     * @return Collection<string, self>
     */
    public static function latestByMonth(string $section): Collection
    {
        return static::query()
            ->section($section)
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            // Ordered oldest first, so keying by month leaves the latest of
            // each one standing.
            ->keyBy('month');
    }
}
