<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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

        if ($request->wantsJson()) {
            return response()->json(['message' => __('store.contact_sent')]);
        }

        return redirect(localized_route('contact.show'))
            ->with('status', __('store.contact_sent'));
    }
}
