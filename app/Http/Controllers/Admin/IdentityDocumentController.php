<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\IdentityDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The only place an identity document can be read, and it says so out loud.
 *
 * Every route here is behind the owner role, and every look at a document is
 * written to the activity log. An access log is the cheapest evidence that
 * access was controlled; its absence is what turns an incident into a finding.
 */
class IdentityDocumentController extends Controller
{
    public function index(): View
    {
        return view('admin.documents.index', [
            'documents' => IdentityDocument::query()
                ->with('user', 'reviewer')
                ->orderByRaw("status = 'pending' desc")
                ->latest()
                ->paginate(30),
        ]);
    }

    /**
     * Streams the decrypted document, inline, once.
     *
     * No download name and no attachment: it is looked at and not filed away
     * on a member of staff's own machine.
     */
    public function show(IdentityDocument $document): Response
    {
        $contents = $document->decrypted();

        abort_if($contents === null, 404);

        AdminActivityLog::record(
            'identity_document.viewed',
            $document,
            'Opened the '.$document->kind.' of '.$document->user?->name,
        );

        return response($contents, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Records the verdict and destroys the document in the same breath.
     *
     * This is the whole point of the feature: the shop keeps proof that it
     * checked somebody's age, and does not keep their passport.
     */
    public function review(Request $request, IdentityDocument $document): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            // Required to verify and meaningless to reject: a refused
            // document has no validity to run out. Read off the document
            // itself, so it must be a date the document could carry.
            'expires_at' => [
                'exclude_unless:status,verified',
                'required',
                'date',
                'after:today',
                'before:'.now()->addYears(20)->toDateString(),
            ],
            'review_note' => ['nullable', 'string', 'max:200'],
        ]);

        $document->forceFill([
            'status' => $validated['status'],
            'expires_at' => $validated['expires_at'] ?? null,
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $request->user()->id,
        ])->save();

        $document->forgetFile();

        AdminActivityLog::record(
            'identity_document.'.$validated['status'],
            $document,
            'Marked the '.$document->kind.' of '.$document->user?->name.' as '.$validated['status'].' and deleted the file',
        );

        return back()->with('status', 'Document reviewed and deleted.');
    }
}
