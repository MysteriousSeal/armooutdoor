@extends('layouts.admin')

@section('title', $conversation->subject)

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/thread.css') }}">
@endpush

@section('content')
    <div class="admin-list-page admin-conversation-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.conversations.index') }}">Messages</a></p>
                    <h2 class="admin-list-title">
                        {{ $conversation->subject }}
                        <span class="admin-status-chip admin-status-chip--closed" id="conversation-closed-chip" @if (! $conversation->isClosed()) hidden @endif>Closed</span>
                    </h2>
                    <p class="admin-list-lede">
                        From {{ $conversation->name }}
                        · <span class="admin-nowrap">started {{ $conversation->created_at->format('d M Y · H:i') }}</span>
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <form method="POST" action="{{ route('admin.conversations.close', $conversation) }}" data-status-form="close" @if ($conversation->isClosed()) hidden @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-secondary">Close</button>
                    </form>
                    <form method="POST" action="{{ route('admin.conversations.reopen', $conversation) }}" data-status-form="reopen" @if (! $conversation->isClosed()) hidden @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-secondary">Reopen</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="admin-order-layout">
            <div class="order-main">
                {{-- One chat surface: the thread scrolls inside its own
                     viewport, opened at the latest message, and the composer
                     sits under it without ever leaving the screen. --}}
                <section class="order-panel admin-thread-panel">
                    <div class="admin-thread-viewport" id="conversation-viewport" tabindex="0" aria-label="Conversation history">
                        @include('partials.conversation-thread', ['conversation' => $conversation, 'viewer' => 'admin'])
                    </div>

                    <div class="thread-closed-note admin-composer-note" id="conversation-closed-note" @if (! $conversation->isClosed()) hidden @endif>
                            <p class="form-hint">This conversation is closed. Reopen it to send a reply.</p>
                            <form method="POST" action="{{ route('admin.conversations.reopen', $conversation) }}" data-status-form="reopen">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-primary">Reopen to reply</button>
                            </form>
                        </div>

                        <form
                            id="conversation-reply-form"
                            method="POST"
                            action="{{ route('admin.conversations.reply', $conversation) }}"
                            class="thread-composer admin-thread-composer"
                            data-thread-item-class="thread-item--admin"
                            @if ($conversation->isClosed()) hidden @endif
                        >
                            @csrf
                            <label for="body" class="sr-only">Reply</label>
                            <textarea id="body" name="body" class="form-control" rows="2" maxlength="5000" required placeholder="Write your reply… ({{ config('app.name') }} signs it, not you)"></textarea>
                            @error('body') <p class="form-error">{{ $message }}</p> @enderror
                            <div class="thread-composer-actions admin-composer-actions">
                                <span class="admin-composer-count" id="composer-count" hidden></span>
                                <span class="admin-composer-hint">⌘⏎ sends</span>
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-loader" aria-hidden="true"></span>
                                    Send reply
                                </button>
                            </div>
                            @if ($conversation->isGuest())
                                <p class="form-hint admin-guest-reply-hint">
                                    No account behind this thread: they read and answer through a private link emailed to {{ $conversation->email }}.
                                </p>
                            @endif
                        </form>
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Customer</h3>
                    <div class="admin-convo-customer">
                        <span class="admin-customer-avatar" aria-hidden="true">{{ $conversation->initials() }}</span>
                        <div class="admin-convo-customer-main">
                            <p class="admin-table-primary">{{ $conversation->name }}</p>
                            <p class="admin-table-sub">
                                {{ $conversation->email }}
                                <button type="button" class="admin-copy-code" data-copy-code="{{ $conversation->email }}" title="Copy email" aria-label="Copy email">
                                    <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                        <rect x="9" y="9" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/>
                                        <path d="M6 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </p>
                        </div>
                    </div>
                    @if ($conversation->user?->isAdmin())
                        <p class="form-hint">This thread belongs to an admin account.</p>
                    @elseif ($conversation->user)
                        <a href="{{ route('admin.customers.show', $conversation->user) }}" class="btn btn-sm btn-secondary admin-convo-fact-btn">View customer page</a>
                    @elseif ($possibleCustomer)
                        <p class="form-hint">Sent without an account, but the email matches a customer.</p>
                        <a href="{{ route('admin.customers.show', $possibleCustomer) }}" class="btn btn-sm btn-secondary admin-convo-fact-btn">Possibly {{ $possibleCustomer->name }}</a>
                    @else
                        <p class="form-hint">No customer account uses this email.</p>
                    @endif
                </section>

                @if ($conversation->order)
                    <section class="order-fact">
                        <h3 class="order-fact-title">About order</h3>
                        <p class="admin-table-primary">{{ $conversation->order->number }}</p>
                        <p class="admin-table-sub">
                            {{ $conversation->order->created_at->format('d M Y') }}
                            · {{ $conversation->order->formattedTotal() }}
                        </p>
                        <span class="badge badge-{{ $conversation->order->status }}">{{ $conversation->order->statusLabel() }}</span>
                        <a href="{{ route('admin.orders.show', $conversation->order) }}" class="btn btn-sm btn-secondary admin-convo-fact-btn">View order</a>
                    </section>
                @endif

                <section class="order-fact">
                    <h3 class="order-fact-title">Thread</h3>
                    <dl class="admin-email-diagnostics">
                        <div>
                            <dt>Messages</dt>
                            <dd>{{ number_format($conversation->messages->count()) }}</dd>
                        </div>
                        <div>
                            <dt>Started</dt>
                            <dd>{{ $conversation->created_at->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt>Last activity</dt>
                            <dd>{{ $conversation->messages->last()?->created_at->format('d M Y · H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-copy-code.js') }}" defer></script>
    <script src="{{ asset('js/conversation-reply.js') }}" defer></script>
    <script src="{{ asset('js/conversation-edit.js') }}" defer></script>
    <script src="{{ asset('js/admin-conversation-page.js') }}" defer></script>
@endpush
