<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationReplyRequest;
use App\Models\AdminActivityLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\ConversationReplied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['closed', 'all'], true)
            ? $request->query('tab')
            : 'open';

        $conversations = Conversation::query()
            ->with(['user', 'order', 'latestMessage'])
            ->withCount('messages')
            ->when($tab === 'open', fn ($query) => $query->open())
            ->when($tab === 'closed', fn ($query) => $query->closed())
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        $guestEmails = $conversations->getCollection()
            ->whereNull('user_id')
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique();

        $possibleCustomersByEmail = User::query()
            ->where('is_admin', false)
            ->whereIn(DB::raw('LOWER(email)'), $guestEmails->all())
            ->get()
            ->keyBy(fn (User $user): string => mb_strtolower($user->email));

        return view('admin.conversations.index', [
            'tab' => $tab,
            'conversations' => $conversations,
            'openCount' => Conversation::query()->open()->count(),
            'closedCount' => Conversation::query()->closed()->count(),
            'totalCount' => Conversation::query()->count(),
            'unreadCount' => Conversation::query()->unreadForAdmin()->count(),
            'thisWeekCount' => Conversation::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'possibleCustomersByEmail' => $possibleCustomersByEmail,
        ]);
    }

    public function show(Conversation $conversation): View
    {
        $conversation->markReadForAdmin();
        $conversation->load(['user', 'order', 'messages']);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'possibleCustomer' => $conversation->possibleCustomer(),
        ]);
    }

    public function reply(StoreConversationReplyRequest $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        // A guest reads through their private link instead of an account —
        // minted here if the thread predates guest links, so the reply email
        // always has somewhere to point.
        if ($conversation->isGuest()) {
            $conversation->ensureGuestToken();
        }

        $message = $conversation->postMessage(
            $request->validated('body'),
            ConversationMessage::AUTHOR_ADMIN,
            $request->user(),
        );

        $conversation->markReadForAdmin();

        AdminActivityLog::record(
            'conversation.replied',
            $conversation,
            'Replied to '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        $this->notifyCustomer($conversation);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Reply sent.',
                'sentAt' => $message->created_at->format('d M Y · H:i'),
                'authorLabel' => $message->authorLabel(),
                'authorInitials' => $message->avatarInitials(),
                'body' => $message->body,
                // So the freshly appended bubble is editable straight away,
                // rather than only after a reload.
                'editUrl' => route('admin.conversations.messages.update', [$conversation, $message]),
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    /**
     * Correcting a reply shortly after sending it. Deliberately does not
     * re-notify the customer: the email only says an answer is waiting, and
     * a typo fix is not news.
     */
    public function updateMessage(
        StoreConversationReplyRequest $request,
        Conversation $conversation,
        ConversationMessage $message,
    ): RedirectResponse|JsonResponse {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->isEditableBy($request->user()), 403);

        $message->body = $request->validated('body');
        $message->edited_at = now();
        $message->save();

        AdminActivityLog::record(
            'conversation.message_edited',
            $conversation,
            'Edited a reply to '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Reply updated.',
                'body' => $message->body,
                'editedLabel' => 'edited at '.$message->edited_at->format('H:i'),
            ]);
        }

        return back()->with('status', 'Reply updated.');
    }

    /**
     * The reply is already saved by the time this runs, so a mail outage must
     * not turn a successful reply into a 500 for the admin — and the SMTP
     * round-trip must not keep the admin waiting either: the send is deferred
     * to after the response has been flushed. Same process, no queue worker
     * to supervise, nothing to sit unsent in a jobs table — the admin is just
     * no longer standing in line behind the mail server.
     */
    private function notifyCustomer(Conversation $conversation): void
    {
        app()->terminating(function () use ($conversation): void {
            try {
                if ($conversation->isGuest()) {
                    Notification::route('mail', $conversation->email)
                        ->notify(new ConversationReplied($conversation));
                } else {
                    $conversation->user->notify(new ConversationReplied($conversation));
                }
            } catch (Throwable $exception) {
                Log::error('Could not email a conversation reply notification.', [
                    'conversation_id' => $conversation->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function close(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $conversation->status = Conversation::STATUS_CLOSED;
        // From when the guest link's thirty days of grace are counted.
        $conversation->closed_at = now();
        $conversation->save();

        AdminActivityLog::record(
            'conversation.closed',
            $conversation,
            'Closed the conversation with '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Conversation closed.', 'status' => $conversation->status]);
        }

        return back()->with('status', 'Conversation closed.');
    }

    public function reopen(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $conversation->status = Conversation::STATUS_OPEN;
        $conversation->closed_at = null;
        $conversation->save();

        AdminActivityLog::record(
            'conversation.reopened',
            $conversation,
            'Reopened the conversation with '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Conversation reopened.', 'status' => $conversation->status]);
        }

        return back()->with('status', 'Conversation reopened.');
    }
}
