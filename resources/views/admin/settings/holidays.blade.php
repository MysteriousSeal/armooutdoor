@extends('layouts.admin')

@section('title', 'Holidays settings')

@section('content')
    @php
        // The add form only shows the errors and values of its own refused submission.
        $creating = old('_form', 'holiday-create') === 'holiday-create';
    @endphp
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Holidays</h2>
                    <p class="admin-list-lede">
                        Days the shop is closed. No parcel leaves from the first day to the last, both included:
                        ship dates move past them, and the shop shows a notice {{ \App\Models\Holiday::ANNOUNCE_DAYS }} days ahead.
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary">Back to settings</a>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.holidays.store') }}" class="admin-form-card admin-form-card--solo admin-holiday-form">
            @csrf
            <input type="hidden" name="_form" value="holiday-create">

            <h3 class="admin-panel-title">Add a closure</h3>

            <div class="form-group">
                <label for="starts_on">First day closed</label>
                <input type="date" id="starts_on" name="starts_on" class="form-control" value="{{ $creating ? old('starts_on') : '' }}" required>
                @if ($creating) @error('starts_on') <p class="form-error">{{ $message }}</p> @enderror @endif
            </div>

            <div class="form-group">
                <label for="ends_on">Last day closed</label>
                <input type="date" id="ends_on" name="ends_on" class="form-control" value="{{ $creating ? old('ends_on') : '' }}" required>
                @if ($creating) @error('ends_on') <p class="form-error">{{ $message }}</p> @enderror @endif
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add holiday</button>
            </div>
        </form>

        @if ($holidays->isEmpty())
            <div class="empty-state">
                <p>No holidays yet.</p>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>First day closed</th>
                            <th>Last day closed</th>
                            <th>Parcels leave again</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($holidays as $holiday)
                            @php
                                $status = match (true) {
                                    $holiday->ends_on->toDateString() < $today => ['Past', 'is-out-of-stock'],
                                    $holiday->starts_on->toDateString() <= $today => ['Closed now', 'is-low-stock'],
                                    default => ['Upcoming', 'is-in-stock'],
                                };
                            @endphp
                            <tr>
                                <td class="admin-table-strong">{{ $holiday->starts_on->format('D j M Y') }}</td>
                                <td>{{ $holiday->ends_on->format('D j M Y') }}</td>
                                <td>{{ $holiday->resumesOn()->format('D j M Y') }}</td>
                                <td><span class="admin-availability-chip {{ $status[1] }}">{{ $status[0] }}</span></td>
                                <td>
                                    <div class="admin-table-actions">
                                        <button type="button" class="btn btn-sm btn-secondary" data-modal-open="holiday-edit-{{ $holiday->id }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.settings.holidays.destroy', $holiday) }}" onsubmit="return confirm('Remove this holiday?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-secondary">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($holidays as $holiday)
                @php
                    $form = 'holiday-edit-'.$holiday->id;
                    // A refused save reopens its own dialog, typed values and errors in place.
                    $refused = old('_form') === $form;
                @endphp
                <dialog id="{{ $form }}" class="modal" aria-labelledby="{{ $form }}-title" @if ($refused && $errors->any()) data-autoopen @endif>
                    <form method="POST" action="{{ route('admin.settings.holidays.update', $holiday) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="{{ $form }}">
                        <p class="modal-kicker">Holiday</p>
                        <h3 class="modal-title" id="{{ $form }}-title">Edit this closure</h3>

                        <div class="form-group">
                            <label for="{{ $form }}-starts_on">First day closed</label>
                            <input type="date" id="{{ $form }}-starts_on" name="starts_on" class="form-control" value="{{ $refused ? old('starts_on') : $holiday->starts_on->toDateString() }}" required>
                            @if ($refused) @error('starts_on') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>

                        <div class="form-group">
                            <label for="{{ $form }}-ends_on">Last day closed</label>
                            <input type="date" id="{{ $form }}-ends_on" name="ends_on" class="form-control" value="{{ $refused ? old('ends_on') : $holiday->ends_on->toDateString() }}" required>
                            @if ($refused) @error('ends_on') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </dialog>
            @endforeach
        @endif
    </div>
@endsection
