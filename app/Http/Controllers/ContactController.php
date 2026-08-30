<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Notifications\GuestConversationStarted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function create(): View
    {
        $user = request()->user();

        return view('contact.show', [
            'company' => CompanySetting::current(),
            'prefillName' => $user?->name ?? old('name', ''),
            'prefillEmail' => $user?->email ?? old('email', ''),
            'identityLocked' => $user !== null,
            'orders' => $user
                ? $user->orders()->whereNull('archived_at')->where('status', '!=', 'draft')->latest()->get()
                : collect(),
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->safe()->except(['website', 'message']);
        $user = $request->user();

        $conversation = Conversation::query()->create([
            ...$validated,
            'name' => $user?->name ?? $validated['name'],
            'email' => $user?->email ?? $validated['email'],
            'user_id' => $user?->id,
        ]);

        $conversation->postMessage(
            $request->safe()->string('message')->toString(),
            ConversationMessage::AUTHOR_CUSTOMER,
            $user,
        );

        // A guest can't come back through an account, so their door is a
        // private link, mailed right away — after the response, so the form
        // never waits on SMTP, and guarded, so a mail outage never turns a
        // delivered message into an error page.
        if ($user === null) {
            $conversation->ensureGuestToken();

            app()->terminating(function () use ($conversation): void {
                try {
                    Notification::route('mail', $conversation->email)
                        ->notify(new GuestConversationStarted($conversation));
                } catch (Throwable $exception) {
                    Log::error('Could not email a guest conversation link.', [
                        'conversation_id' => $conversation->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => __('store.contact_sent')]);
        }

        return redirect(localized_route('contact.show'))
            ->with('status', __('store.contact_sent'));
    }
}
