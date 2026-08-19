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

        <nav class="admin-tabs" aria-label="Admin tabs">
            <a href="{{ route('admin.settings.admins.index') }}" class="{{ $tab === 'active' ? 'active' : '' }}">
                Active <span class="admin-tab-count">{{ number_format($activeCount) }}</span>
            </a>
            <a href="{{ route('admin.settings.admins.index', ['tab' => 'deactivated']) }}" class="{{ $tab === 'deactivated' ? 'active' : '' }}">
                Deactivated <span class="admin-tab-count">{{ number_format($deactivatedCount) }}</span>
            </a>
        </nav>

        @if ($admins->isEmpty())
            <div class="empty-state">
                @if ($tab === 'active')
                    <p>No admins yet.</p>
                    <a href="{{ route('admin.settings.admins.create') }}" class="btn btn-primary">Add admin</a>
                @else
                    <p>No deactivated admins.</p>
                @endif
            </div>
        @else
            <div class="admin-user-grid">
                @foreach ($admins as $admin)
                    @if ($tab === 'active')
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
                    @else
                        <div class="admin-user-card admin-user-card--deactivated">
                            <span class="admin-user-avatar admin-user-avatar--deactivated">{{ $admin->initials() }}</span>
                            <span class="admin-user-info">
                                <span class="admin-user-name">{{ $admin->name }}</span>
                                <span class="admin-user-email">{{ $admin->email }}</span>
                                <span class="admin-user-since">Deactivated {{ $admin->admin_deactivated_at->format('d M Y') }}</span>
                            </span>
                            <form method="POST" action="{{ route('admin.settings.admins.reactivate', $admin) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-secondary">Reactivate</button>
                            </form>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection
