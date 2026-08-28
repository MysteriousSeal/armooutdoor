@extends('layouts.admin')

@section('title', 'Email test')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
            <h2 class="admin-list-title">Email test</h2>
            <p class="admin-list-lede">
                Sends a branded test email wherever you point it, to prove the shop can reach an inbox — and shows the transport it would use.
            </p>
        </header>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel admin-email-test-panel">
                    <h3 class="order-panel-title">Send a test email</h3>
                    <p class="admin-email-test-hint">
                        The email says when it was sent and through what, so two tests can't be mistaken for one another.
                    </p>
                    <form method="POST" action="{{ route('admin.settings.email.test') }}" class="admin-email-test-form">
                        @csrf
                        <div class="form-group">
                            <label for="test-email">Send to</label>
                            <div class="admin-email-test-row">
                                <input
                                    id="test-email"
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="you@example.com"
                                    value="{{ old('email', auth()->user()->email) }}"
                                    maxlength="255"
                                    required
                                >
                                <button type="submit" class="btn btn-primary">Send test email</button>
                            </div>
                            @error('email') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </form>

                    @if ($mailer === 'log')
                        <p class="admin-email-test-warning">
                            The active transport is <strong>log</strong> — emails are written to <code>storage/logs</code> instead of being sent. Real delivery needs an SMTP transport in the environment file.
                        </p>
                    @endif
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Current configuration</h3>
                    <p class="admin-email-test-hint">Read from the environment — no secrets shown, only whether they're filled in.</p>
                    <dl class="admin-email-diagnostics">
                        @foreach ($diagnostics as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </aside>
        </div>
    </div>
@endsection
