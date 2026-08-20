<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
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
        $validated = $request->safe()->except('website');

        ContactMessage::query()->create([
            ...$validated,
            'name' => $request->user()?->name ?? $validated['name'],
            'email' => $request->user()?->email ?? $validated['email'],
            'user_id' => $request->user()?->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => __('store.contact_sent')]);
        }

        return redirect(localized_route('contact.show'))
            ->with('status', __('store.contact_sent'));
    }
}
