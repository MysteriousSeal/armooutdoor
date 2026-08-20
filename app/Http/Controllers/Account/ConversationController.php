<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationReplyRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.conversations.index', [
            'conversations' => $request->user()->conversations()
                ->with('latestMessage')
                ->withCount('messages')
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeOwnership($request, $conversation);

        $conversation->markReadForCustomer();
        $conversation->load(['order', 'messages']);

        return view('account.conversations.show', [
            'conversation' => $conversation,
        ]);
    }

    public function reply(StoreConversationReplyRequest $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $conversation);

        // The composer is hidden on a closed thread, but a customer reply
        // reopens it — that should be a deliberate action, not something a
        // stale page can trigger by accident.
        abort_if($conversation->isClosed(), 403);

        $message = $conversation->postMessage(
            $request->validated('body'),
            ConversationMessage::AUTHOR_CUSTOMER,
            $request->user(),
        );

        $conversation->markReadForCustomer();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('store.conversation_reply_sent'),
                'sentAt' => $message->created_at->format('d/m/Y · H:i'),
                'authorLabel' => $message->authorLabel(),
                'body' => $message->body,
            ]);
        }

        return back()->with('status', __('store.conversation_reply_sent'));
    }

    /**
     * A customer may only ever reach their own threads. 404 rather than 403 so
     * the existence of someone else's thread isn't confirmed.
     */
    private function authorizeOwnership(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }
}
