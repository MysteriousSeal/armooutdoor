<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
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
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $validated = $request->safe()->except('website');

        ContactMessage::query()->create([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        return redirect(localized_route('contact.show'))
            ->with('status', __('store.contact_sent'));
    }
}
