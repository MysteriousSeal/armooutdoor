@extends('layouts.admin')

@section('title', $admin->exists ? 'Edit admin' : 'Add admin')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.admins.index') }}">Admins</a></p>
                    <h2 class="admin-list-title">{{ $admin->exists ? 'Edit admin' : 'Add admin' }}</h2>
                    <p class="admin-list-lede">
                        {{ $admin->exists ? 'Update their name, email, or reset their password.' : 'They\'ll be able to sign in to the back office right away with this password.' }}
                    </p>
                </div>
                <a href="{{ route('admin.settings.admins.index') }}" class="btn btn-secondary">Back to admins</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $admin->exists ? route('admin.settings.admins.update', $admin) : route('admin.settings.admins.store') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($admin->exists)
                @method('PUT')
            @endif

            <div class="form-row form-row--inline">
                <div class="form-group">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $admin->first_name) }}" required maxlength="80">
                    @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $admin->last_name) }}" required maxlength="80">
                    @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $admin->email) }}" required maxlength="255">
                @error('email') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @php $selectedRole = old('role', $admin->role ?? 'staff'); @endphp
            <div class="form-group">
                <label>Role</label>
                <div class="role-picker">
                    <label class="role-option {{ $selectedRole === 'owner' ? 'is-selected' : '' }}">
                        <input type="radio" name="role" value="owner" @checked($selectedRole === 'owner')>
                        <span class="role-option-title">Owner</span>
                        <span class="role-option-desc">Full access, including refunds, deleting discounts, Stripe payment data, and managing admins.</span>
                    </label>
                    <label class="role-option {{ $selectedRole === 'staff' ? 'is-selected' : '' }}">
                        <input type="radio" name="role" value="staff" @checked($selectedRole === 'staff')>
                        <span class="role-option-title">Staff</span>
                        <span class="role-option-desc">Everything except refunds, deleting discounts, Stripe payment data, and managing admins.</span>
                    </label>
                </div>
                @error('role') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-row form-row--inline">
                <div class="form-group">
                    <label for="password">{{ $admin->exists ? 'New password' : 'Password' }}</label>
                    <input type="password" id="password" name="password" class="form-control" @unless($admin->exists) required @endunless autocomplete="new-password">
                    <p class="form-hint">{{ $admin->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</p>
                    @error('password') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" @unless($admin->exists) required @endunless autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $admin->exists ? 'Save changes' : 'Add admin' }}</button>
                <a href="{{ route('admin.settings.admins.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        @if ($admin->exists && ! $admin->is(auth()->user()))
            <form
                method="POST"
                action="{{ route('admin.settings.admins.deactivate', $admin) }}"
                class="admin-form-card admin-form-card--solo admin-form-card--danger"
            >
                @csrf
                @method('PATCH')
                <h3 class="admin-panel-title">Deactivate</h3>
                <p class="form-hint">Revokes back-office access. They stay a regular account otherwise.</p>
                <div class="form-actions">
                    <button type="submit" class="btn btn-secondary">Deactivate admin</button>
                </div>
            </form>
        @endif
    </div>
@endsection
