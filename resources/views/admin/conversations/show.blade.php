@extends('layouts.admin')

@section('title', $conversation->subject)

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/thread.css') }}">
@endpush

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.conversations.index') }}">Messages</a></p>
                    <h2 class="admin-list-title">
                        {{ $conversation->subject }}
                        @if ($conversation->isClosed())
                            <span class="admin-status-chip admin-status-chip--closed">Closed</span>
                        @endif
                    </h2>
                    <p class="admin-list-lede">
                        From {{ $conversation->name }} ({{ $conversation->email }})
                        · <span class="admin-nowrap">started {{ $conversation->created_at->format('d M Y · H:i') }}</span>
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    @if ($conversation->isClosed())
                        <form method="POST" action="{{ route('admin.conversations.reopen', $conversation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-secondary">Reopen</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.conversations.close', $conversation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-secondary">Close</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($conversation->user || $conversation->order || $possibleCustomer)
                <div class="admin-message-links">
                    @if ($conversation->user?->isAdmin())
                        <span class="admin-message-link-chip admin-message-link-chip--admin">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 3 4.5 6v5.5c0 4.3 3.1 8 7.5 9 4.4-1 7.5-4.7 7.5-9V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Admin
                        </span>
                    @elseif ($conversation->user)
                        <a href="{{ route('admin.customers.show', $conversation->user) }}" class="admin-message-link-chip">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm-7 9a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Customer account
                        </a>
                    @elseif ($possibleCustomer)
                        <a href="{{ route('admin.customers.show', $possibleCustomer) }}" class="admin-message-link-chip admin-message-link-chip--guess">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm-7 9a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Possibly {{ $possibleCustomer->name }}
                        </a>
                    @endif
                    @if ($conversation->order)
                        <a href="{{ route('admin.orders.show', $conversation->order) }}" class="admin-message-link-chip">
                            <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                <path d="M4 7h16v13H4V7Zm4-3.5v3.5m8-3.5v3.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Order {{ $conversation->order->number }}
                        </a>
                    @endif
                </div>
            @endif
        </header>

        @include('partials.conversation-thread', ['conversation' => $conversation, 'viewer' => 'admin'])

        @if ($conversation->isGuest())
            {{-- No account behind this thread, so a reply here would have no reader. --}}
            <section class="order-panel thread-guest-note">
                <h3 class="order-panel-title">Sent without an account</h3>
                <p class="form-hint">
                    This message came from someone who wasn't signed in, so there's no account for them to
                    read a reply in. Answer them by email instead.
                </p>
                <div class="admin-message-actions">
                    <a href="mailto:{{ $conversation->email }}?subject={{ rawurlencode('Re: '.$conversation->subject) }}" class="btn btn-primary">Reply by email</a>
                </div>
            </section>
        @elseif ($conversation->isClosed())
            <section class="order-panel thread-closed-note">
                <p class="form-hint">This conversation is closed. Reopen it to send a reply.</p>
                <form method="POST" action="{{ route('admin.conversations.reopen', $conversation) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">Reopen to reply</button>
                </form>
            </section>
        @else
            <form
                id="conversation-reply-form"
                method="POST"
                action="{{ route('admin.conversations.reply', $conversation) }}"
                class="order-panel thread-composer"
                data-thread-item-class="thread-item--admin"
            >
                @csrf
                <label for="body" class="order-panel-title">Reply</label>
                <p class="form-hint">{{ $conversation->name }} sees this as being from {{ config('app.name') }}, not from you.</p>
                <textarea id="body" name="body" class="form-control" rows="5" maxlength="5000" required placeholder="Write your reply…"></textarea>
                @error('body') <p class="form-error">{{ $message }}</p> @enderror
                <div class="thread-composer-actions">
                    <button type="submit" class="btn btn-primary">Send reply</button>
                </div>
            </form>
        @endif

        <div class="admin-message-actions">
            <a href="{{ route('admin.conversations.index') }}" class="btn btn-secondary">Back to messages</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/conversation-reply.js') }}" defer></script>
@endpush
