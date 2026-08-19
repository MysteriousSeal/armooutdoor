@extends('layouts.admin')

@section('title', 'Admins')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Admins</h2>
                    <p class="admin-list-lede">Who can sign in to the back office.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.settings.admins.create') }}" class="btn btn-primary">Add admin</a>
                </div>
            </div>
        </header>

        @if ($admins->isEmpty())
            <div class="empty-state">
                <p>No admins yet.</p>
                <a href="{{ route('admin.settings.admins.create') }}" class="btn btn-primary">Add admin</a>
            </div>
        @else
            <div class="admin-user-grid">
                @foreach ($admins as $admin)
                    <a href="{{ route('admin.settings.admins.edit', $admin) }}" class="admin-user-card">
                        <span class="admin-user-avatar">{{ $admin->initials() }}</span>
                        <span class="admin-user-info">
                            <span class="admin-user-name">
                                {{ $admin->name }}
                                @if ($admin->is(auth()->user()))
                                    <span class="admin-user-you">You</span>
                                @endif
                            </span>
                            <span class="admin-user-email">{{ $admin->email }}</span>
                            <span class="admin-user-since">Added {{ $admin->created_at->format('d M Y') }}</span>
                        </span>
                        <svg class="admin-user-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                            <path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
