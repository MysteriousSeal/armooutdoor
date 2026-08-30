<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The thread page for someone who wrote without an account.
 *
 * The token in the URL is the whole key: long, random, sent only to the
 * email address the guest gave. Owning the inbox is what proves the visitor
 * is the author — the same proof a password reset relies on.
 */
class GuestConversationController extends Controller
{
    public function show(string $token): View
    {
        $conversation = $this->conversationFor($token);
        $conversation->markReadForCustomer();
        $conversation->load('messages');

        return view('conversations.guest', [
            'conversation' => $conversation,
        ]);
    }

    public function reply(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $conversation = $this->conversationFor($token);

        // A closed thread still reads, but no longer answers.
        abort_if($conversation->isClosed(), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->postMessage(
            $validated['body'],
            ConversationMessage::AUTHOR_CUSTOMER,
            null,
        );

        $conversation->markReadForCustomer();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('store.conversation_reply_sent'),
                'sentAt' => $message->created_at->format('d/m/Y · H:i'),
                'authorLabel' => $message->authorLabel(),
                'authorInitials' => $message->avatarInitials(),
                'body' => $message->body,
            ]);
        }

        return back()->with('status', __('store.conversation_reply_sent'));
    }

    /**
     * The thread behind a token, or no page at all: an unknown token and an
     * expired link both read as 404, so the URL never confirms what once
     * existed.
     */
    private function conversationFor(string $token): Conversation
    {
        $conversation = Conversation::query()
            ->where('guest_token', $token)
            ->first();

        abort_if($conversation === null || ! $conversation->guestLinkUsable(), 404);

        return $conversation;
    }
}
