@php
    // Shared by the admin thread view and the customer account thread view.
    // $viewer is 'admin' or 'customer' and decides which side reads as "mine"
    // and which language the dates are written in — the admin is in English,
    // the storefront in French.
    $isAdminView = ($viewer ?? 'customer') === 'admin';
    $messages = $conversation->messages;
    $editor = $isAdminView ? auth()->user() : null;
@endphp

<ol class="thread thread--as-{{ $isAdminView ? 'admin' : 'customer' }}" id="conversation-thread">
    @foreach ($messages as $message)
        @php
            $previous = $loop->index > 0 ? $messages[$loop->index - 1] : null;
            $continues = $message->continues($previous);
            $startsNewDay = $previous === null || ! $previous->created_at->isSameDay($message->created_at);

            $day = $isAdminView
                ? $message->created_at->format('d M Y')
                : $message->created_at->translatedFormat('j F Y');
            $relative = $isAdminView
                ? $message->created_at->diffForHumans(['locale' => 'en'])
                : $message->created_at->diffForHumans();
            $exact = $isAdminView
                ? $message->created_at->format('d M Y · H:i')
                : $message->created_at->translatedFormat('j F Y · H:i');
        @endphp

        @if ($startsNewDay)
            <li class="thread-day" role="presentation">
                <span>{{ $day }}</span>
            </li>
        @endif

        @php $editable = $message->isEditableBy($editor); @endphp

        <li
            class="thread-item {{ $message->isFromAdmin() ? 'thread-item--admin' : 'thread-item--customer' }} {{ $continues ? 'thread-item--continues' : '' }}"
            @if ($editable) data-message-id="{{ $message->id }}" data-edit-url="{{ route('admin.conversations.messages.update', [$conversation, $message]) }}" @endif
        >
            @if ($continues)
                <span class="thread-avatar thread-avatar--placeholder" aria-hidden="true"></span>
            @else
                <span class="thread-avatar" aria-hidden="true">{{ $message->avatarInitials() }}</span>
            @endif

            <div class="thread-bubble">
                @unless ($continues)
                    <div class="thread-meta">
                        <span class="thread-author">{{ $message->authorLabel() }}</span>
                        <time class="thread-time" datetime="{{ $message->created_at->toIso8601String() }}" title="{{ $exact }}">
                            {{ $relative }}
                        </time>
                    </div>
                @endunless
                <p class="thread-body">{!! nl2br(e($message->body)) !!}</p>

                <div class="thread-foot">
                    @if ($continues)
                        <time class="thread-time" datetime="{{ $message->created_at->toIso8601String() }}" title="{{ $exact }}">
                            {{ $relative }}
                        </time>
                    @endif

                    <span class="thread-edited" @unless ($message->wasEdited()) hidden @endunless>
                        @if ($message->wasEdited())
                            {{ $isAdminView
                                ? 'edited at '.$message->edited_at->format('H:i')
                                : __('store.conversation_edited_at', ['time' => $message->edited_at->format('H\hi')]) }}
                        @endif
                    </span>

                    @if ($editable)
                        <button type="button" class="thread-edit-btn" data-thread-edit>Edit</button>
                    @endif
                </div>
            </div>
        </li>
    @endforeach
</ol>
