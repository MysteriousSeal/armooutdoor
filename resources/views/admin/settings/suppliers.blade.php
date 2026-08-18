@extends('layouts.admin')

@section('title', 'Suppliers settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Suppliers</h2>
                    <p class="admin-list-lede">Suppliers you order stock from, and how long they usually take to deliver.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary">Back to settings</a>
                    <a href="{{ route('admin.settings.suppliers.create') }}" class="btn btn-primary">Add supplier</a>
                </div>
            </div>
        </header>

        @if ($suppliers->isEmpty())
            <div class="empty-state">
                <p>No suppliers yet.</p>
                <a href="{{ route('admin.settings.suppliers.create') }}" class="btn btn-primary">Add supplier</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Website</th>
                            <th>Lead time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.settings.suppliers.edit', $supplier) }}" class="admin-table-strong">
                                        {{ $supplier->name }}
                                    </a>
                                </td>
                                <td>
                                    @if ($supplier->website)
                                        <a href="{{ $supplier->website }}" target="_blank" rel="noopener noreferrer">
                                            {{ $supplier->website }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->lead_time_days !== null)
                                        {{ $supplier->lead_time_days }} {{ $supplier->lead_time_days === 1 ? 'day' : 'days' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.settings.suppliers.edit', $supplier) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
