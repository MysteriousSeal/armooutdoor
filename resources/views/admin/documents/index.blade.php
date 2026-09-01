@extends('layouts.admin')

@section('title', 'Identity documents')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Compliance</p>
                    <h2 class="admin-list-title">Identity documents</h2>
                    <p class="admin-list-lede">
                        Proof of age for restricted items. Opening one is written to the activity log, and
                        marking it reviewed deletes the file and keeps only the verdict.
                    </p>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($documents->total()) }} {{ \Illuminate\Support\Str::plural('document', $documents->total()) }}</span>
            </div>
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table admin-documents-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Document</th>
                        <th>Sent</th>
                        <th>Valid until</th>
                        <th>Status</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td>
                                {{ $document->user?->name ?? '—' }}
                                <span class="doc-admin-sub">{{ $document->user?->email }}</span>
                            </td>
                            <td>
                                {{ ucfirst(str_replace('_', ' ', $document->kind)) }}
                                @if ($document->fileExists())
                                    {{-- A new tab, never a download: it is looked at and not
                                         filed away on somebody's own machine. --}}
                                    <a href="{{ route('admin.documents.show', $document) }}" target="_blank" rel="noopener" class="doc-admin-open">Open</a>
                                @else
                                    <span class="doc-admin-sub">File deleted</span>
                                @endif
                            </td>
                            <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($document->expires_at)
                                    <span class="doc-admin-valid{{ $document->hasExpired() ? ' is-lapsed' : '' }}">
                                        {{ $document->expires_at->format('d/m/Y') }}
                                    </span>
                                    @if ($document->hasExpired())
                                        <span class="doc-admin-sub">Lapsed</span>
                                    @endif
                                @else
                                    {{-- Nothing to show until somebody verifies it: the date
                                         is read off the document at that moment. --}}
                                    <span class="doc-admin-sub">—</span>
                                @endif
                            </td>
                            <td>
                                @php ($state = $document->effectiveStatus())
                                <span class="badge badge-{{ ['verified' => 'active', 'rejected' => 'refunded', 'expired' => 'refunded'][$state] ?? 'featured' }}">
                                    {{ $state }}
                                </span>
                                @if ($document->reviewed_at)
                                    <span class="doc-admin-sub">{{ $document->reviewer?->name }}, {{ $document->reviewed_at->format('d/m/Y') }}</span>
                                @endif

                            </td>
                            <td>
                                @if ($document->isPending())
                                    <form method="POST" action="{{ route('admin.documents.review', $document) }}" class="doc-admin-review">
                                        @csrf
                                        @method('PATCH')
                                        {{-- Read off the document. Required to verify,
                                             ignored when rejecting. --}}
                                        <input
                                            type="date"
                                            name="expires_at"
                                            class="form-control doc-admin-date"
                                            aria-label="Valid until"
                                            title="Valid until"
                                            min="{{ now()->addDay()->toDateString() }}"
                                            max="{{ now()->addYears(20)->toDateString() }}"
                                            value="{{ old('expires_at') }}"
                                        >
                                        <input type="text" name="review_note" class="form-control doc-admin-note" maxlength="200" placeholder="Note (optional)">
                                        <button type="submit" name="status" value="verified" class="btn btn-sm btn-primary">Verified</button>
                                        <button type="submit" name="status" value="rejected" class="btn btn-sm btn-secondary">Rejected</button>
                                        @error('expires_at') <span class="doc-admin-error">{{ $message }}</span> @enderror
                                    </form>
                                @else
                                    <span class="doc-admin-sub">{{ $document->review_note ?: '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><span class="doc-admin-sub">No documents.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $documents->links() }}
    </div>
@endsection
