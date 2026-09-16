@extends('layouts.admin')

@section('title', 'Sessions')

@section('content')
    <div class="fftir-page">
        <header class="fftir-hero">
            <div class="fftir-hero-row">
                <div>
                    <p class="fftir-eyebrow"><a href="{{ route('admin.fftir.index') }}">FFTIR</a></p>
                    <h2 class="fftir-title">Sessions</h2>
                    <p class="fftir-lede">Range visits logged so far, with the ammo each one consumed.</p>
                </div>
                <div class="fftir-actions">
                    <a href="{{ route('admin.fftir.index') }}" class="btn btn-secondary">Back to FFTIR</a>
                    <a href="{{ route('admin.fftir.sessions.create') }}" class="btn btn-primary">Log a session</a>
                </div>
            </div>
        </header>

        @if ($sessions->isEmpty())
            <div class="fftir-empty">
                <p>No sessions logged yet.</p>
                <a href="{{ route('admin.fftir.sessions.create') }}" class="btn btn-primary">Log a session</a>
            </div>
        @else
            <div class="fftir-table-wrap">
                <table class="fftir-table fftir-sessions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Weapon</th>
                            <th>Ammunition</th>
                            <th>Distance</th>
                            <th>Ammo used</th>
                            <th></th>
                            <th>Note</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            @foreach ($session->lines as $line)
                                <tr @if ($loop->first && ! $loop->parent->first) class="fftir-group-start" @endif>
                                    @if ($loop->first)
                                        <td rowspan="{{ $session->lines->count() }}" class="fftir-num">
                                            {{ $session->date->format('d/m/Y') }}
                                        </td>
                                    @endif
                                    <td>{{ $line->weapon->brand }} {{ $line->weapon->model }}</td>
                                    <td>
                                        @if ($line->ammunition)
                                            {{ $line->ammunition->brand }} {{ $line->ammunition->denomination }}
                                        @else
                                            {{ $line->caliber->value }} <span class="fftir-sub">(unspecified box)</span>
                                        @endif
                                    </td>
                                    <td>{{ $line->distance->value }}</td>
                                    <td class="fftir-num">{{ number_format($line->quantity) }}</td>
                                    <td>
                                        <a href="{{ route('admin.fftir.sessions.lines.edit', [$session, $line]) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </td>
                                    @if ($loop->first)
                                        <td rowspan="{{ $session->lines->count() }}">{{ $session->note ?: 'N/A' }}</td>
                                        <td rowspan="{{ $session->lines->count() }}">
                                            <button type="button" class="btn btn-sm btn-secondary" data-modal-open="session-delete-modal-{{ $session->id }}">Delete</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($sessions as $session)
                <dialog id="session-delete-modal-{{ $session->id }}" class="modal fftir-modal" aria-labelledby="session-delete-title-{{ $session->id }}">
                    <form method="POST" action="{{ route('admin.fftir.sessions.destroy', $session) }}">
                        @csrf
                        @method('DELETE')
                        <p class="fftir-modal-kicker">{{ $session->date->format('d/m/Y') }}</p>
                        <h3 class="fftir-modal-title" id="session-delete-title-{{ $session->id }}">Delete this session?</h3>
                        <p class="fftir-modal-hint">
                            Any ammo it deducted will be added back to stock. This can't be undone.
                        </p>
                        <div class="fftir-modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Delete session</button>
                        </div>
                    </form>
                </dialog>
            @endforeach
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/admin-fftir.css') }}">
@endpush
