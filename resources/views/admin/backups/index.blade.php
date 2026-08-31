{{--
    Backups of everything the code cannot recreate.

    `$backups` holds what has already been taken, newest first, and
    `$totalSize` what they weigh together. An archive is written in the
    request, so the button says as much before it is pressed.
--}}
@extends('layouts.admin')

@section('title', 'Backups')

@section('content')
    <div class="admin-list-page admin-backups-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">System</p>
                    <h2 class="admin-list-title">Backups</h2>
                    <p class="admin-list-lede">
                        The database, the product photographs and the private files — everything the code cannot recreate. The code itself lives in git and is left out.
                    </p>
                </div>
                <div class="admin-order-actions">
                    {{-- Written in the request, so the wait is announced rather
                         than sprung. --}}
                    <form method="POST" action="{{ route('admin.backups.store') }}" data-backup-form>
                        @csrf
                        <button type="submit" class="btn btn-primary" data-backup-submit>Back up the site</button>
                    </form>
                </div>
            </div>
            @if ($backups->isNotEmpty())
                <div class="admin-list-meta">
                    <span class="admin-list-chip">{{ trans_choice('{1}:count backup|[2,*]:count backups', $backups->count(), ['count' => $backups->count()]) }}</span>
                    <span class="admin-list-chip">{{ format_bytes($totalSize) }} on disk</span>
                </div>
            @endif
        </header>

        <p class="backup-warning">
            Writing an archive takes about a minute on a full catalogue. Leave the page open until it comes back.
        </p>

        @if ($backups->isEmpty())
            <div class="backup-empty">
                <p class="empty-state">No backup yet.</p>
            </div>
        @else
            <section class="backup-panel" aria-label="Archives">
                <ul class="backup-list">
                    @foreach ($backups as $backup)
                        <li class="backup-item {{ $loop->first ? 'is-latest' : '' }}">
                            <div class="backup-item-main">
                                <span class="backup-item-when">{{ $backup['taken_at']->format('j M Y') }} at {{ $backup['taken_at']->format('H:i') }}</span>
                                <span class="backup-item-ago">{{ admin_relative_date($backup['taken_at']) }}</span>
                                <span class="backup-name">{{ $backup['name'] }}</span>
                            </div>
                            <span class="backup-item-size">{{ format_bytes($backup['size']) }}</span>
                            <div class="backup-actions">
                                <a href="{{ route('admin.backups.show', ['name' => $backup['name']]) }}" class="btn btn-secondary btn-small">Download</a>
                                {{-- Asks first: an archive is the only copy of
                                     what it holds. --}}
                                <button
                                    type="button"
                                    class="accounting-row-btn is-danger"
                                    data-modal-open="backup-delete-modal"
                                    data-backup-delete
                                    data-backup-action="{{ route('admin.backups.destroy', ['name' => $backup['name']]) }}"
                                    data-backup-label="{{ $backup['name'] }}"
                                    aria-label="Delete {{ $backup['name'] }}"
                                    title="Delete this backup"
                                >
                                    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                        <path d="M5 7h14M10 7V5h4v2m-8 0 1 13h10l1-13" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <dialog id="backup-delete-modal" class="modal" aria-labelledby="backup-delete-title">
            <form method="POST" id="backup-delete-form">
                @csrf
                @method('DELETE')
                <h3 class="modal-title" id="backup-delete-title">Delete this backup?</h3>
                <p class="modal-body"><span id="backup-delete-label"></span> will be removed from the server. Nothing else is touched.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </dialog>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-backups.js') }}" defer></script>
@endpush
