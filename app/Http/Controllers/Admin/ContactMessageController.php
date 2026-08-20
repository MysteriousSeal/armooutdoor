<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(): View
    {
        $messages = ContactMessage::query()->with(['user', 'order'])->latest()->simplePaginate(20);

        $guestEmails = $messages->getCollection()
            ->whereNull('user_id')
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique();

        $possibleCustomersByEmail = User::query()
            ->where('is_admin', false)
            ->whereIn(DB::raw('LOWER(email)'), $guestEmails->all())
            ->get()
            ->keyBy(fn (User $user): string => mb_strtolower($user->email));

        return view('admin.messages.index', [
            'messages' => $messages,
            'totalCount' => ContactMessage::query()->count(),
            'unreadCount' => ContactMessage::query()->whereNull('read_at')->count(),
            'thisWeekCount' => ContactMessage::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'possibleCustomersByEmail' => $possibleCustomersByEmail,
        ]);
    }

    public function show(ContactMessage $message): View
    {
        $message->markAsRead();

        return view('admin.messages.show', [
            'message' => $message,
            'possibleCustomer' => $message->possibleCustomer(),
        ]);
    }
}
