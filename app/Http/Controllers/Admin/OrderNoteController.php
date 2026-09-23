<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Notes the admins leave on an order for each other. */
class OrderNoteController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [], ['body' => 'note']);

        $order->notes()->create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        AdminActivityLog::record('order.note_added', $order, 'Added a note on order '.$order->number);

        return redirect()
            ->to(route('admin.orders.show', $order).'#order-notes')
            ->with('status', 'Note added.');
    }

    public function destroy(Request $request, Order $order, OrderNote $note): RedirectResponse
    {
        abort_unless($note->order_id === $order->id, 404);
        abort_unless($note->canBeDeletedBy($request->user()), 403);

        $note->delete();

        AdminActivityLog::record('order.note_deleted', $order, 'Deleted a note on order '.$order->number);

        return redirect()
            ->to(route('admin.orders.show', $order).'#order-notes')
            ->with('status', 'Note deleted.');
    }
}
