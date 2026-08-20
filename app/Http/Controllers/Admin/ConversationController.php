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
        // A guest has no account to read a reply in. Writing one would put an
        // answer somewhere nobody can ever reach.
        abort_if($conversation->isGuest(), 403);

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
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    /**
     * The reply is already saved by the time this runs, so a mail outage must
     * not turn a successful reply into a 500 for the admin. Sent inline rather
     * than queued: no worker is supervised yet, and a queued notification with
     * no worker would sit in the jobs table unnoticed. Add ShouldQueue to the
     * notification once a worker is running.
     */
    private function notifyCustomer(Conversation $conversation): void
    {
        try {
            $conversation->user?->notify(new ConversationReplied($conversation));
        } catch (Throwable $exception) {
            Log::error('Could not email a conversation reply notification.', [
                'conversation_id' => $conversation->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function close(Conversation $conversation): RedirectResponse
    {
        $conversation->status = Conversation::STATUS_CLOSED;
        $conversation->save();

        AdminActivityLog::record(
            'conversation.closed',
            $conversation,
            'Closed the conversation with '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        return back()->with('status', 'Conversation closed.');
    }

    public function reopen(Conversation $conversation): RedirectResponse
    {
        $conversation->status = Conversation::STATUS_OPEN;
        $conversation->save();

        AdminActivityLog::record(
            'conversation.reopened',
            $conversation,
            'Reopened the conversation with '.$conversation->name.' about "'.$conversation->subject.'"',
        );

        return back()->with('status', 'Conversation reopened.');
    }
}
