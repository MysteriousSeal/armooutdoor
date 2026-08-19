@extends('layouts.admin')

@section('title', 'Access denied')

@section('content')
    <div class="admin-list-page">
        <div class="admin-403">
            <span class="admin-403-icon">
                <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
                    <rect x="5" y="11" width="14" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
                    <path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
            </span>
            <h2 class="admin-403-title">You don't have access to this</h2>
            <p class="admin-403-lede">This area is limited to owners. Ask an owner if you think you should have access.</p>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Back to dashboard</a>
        </div>
    </div>
@endsection
