<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch of products sent to Octopia, with the report it came back with.
 */
#[Fillable(['octopia_template_id', 'package_id', 'kind', 'lines', 'report', 'checked_at'])]
class OctopiaSubmission extends Model
{
    /** Octopia has said its last word on a line once it is one of these. */
    private const FINAL = ['Integrated', 'Refused', 'Rejected', 'Duplicated'];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'report' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OctopiaTemplate::class, 'octopia_template_id');
    }

    public function isOffers(): bool
    {
        return $this->kind === 'offers';
    }

    /**
     * Each line with what Octopia answered for it, if it has yet.
     *
     * A product sheet is answered by EAN, with a status and lists of errors
     * and warnings. An offer is answered by its own reference, with an
     * integration status and the results that explain it; those are put in
     * the same shape, so a page reads both alike.
     *
     * @return list<array{gtin: string, reference: string, title: string, status: ?string, operation: ?string, errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}>
     */
    public function outcomes(): array
    {
        $offers = $this->isOffers();
        $report = collect($this->report ?? [])->keyBy(fn (array $item): string => (string) ($offers
            ? ($item['sellerExternalReference'] ?? '')
            : ($item['gtin'] ?? '')));

        return array_map(function (array $line) use ($report, $offers): array {
            $item = $report->get((string) ($offers ? $line['reference'] : $line['gtin']));

            if ($offers) {
                $status = $item['integrationStatus'] ?? null;

                return $line + [
                    'status' => $status,
                    'operation' => null,
                    // The results of an integrated offer are confirmations.
                    'errors' => $status === 'Integrated' ? [] : array_map(fn (array $result): array => [
                        'code' => $result['resultCode'] ?? null,
                        'field' => '',
                        'message' => $result['message'] ?? null,
                    ], $item['results'] ?? []),
                    'warnings' => [],
                ];
            }

            return $line + [
                'status' => $item['status'] ?? null,
                'operation' => $item['operationType'] ?? null,
                'errors' => $item['errors'] ?? [],
                'warnings' => $item['warnings'] ?? [],
            ];
        }, $this->lines);
    }

    /** Whether every line has an answer that will not change. */
    public function isSettled(): bool
    {
        foreach ($this->outcomes() as $outcome) {
            if (! in_array($outcome['status'], self::FINAL, true)) {
                return false;
            }
        }

        return true;
    }
}
