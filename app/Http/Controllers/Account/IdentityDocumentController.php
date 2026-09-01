<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\IdentityDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IdentityDocumentController extends Controller
{
    private const MAX_KILOBYTES = 8192;

    public function index(Request $request): View
    {
        return view('account.documents.index', [
            'documents' => $request->user()->identityDocuments()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:'.implode(',', IdentityDocument::KINDS)],
            // Images and PDF only, capped: a document nobody can open is no
            // proof, and an archive would be a way in.
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_KILOBYTES],
        ]);

        $file = $validated['document'];

        // Encrypted before it is written, so the plaintext never lands on
        // disk even briefly, and a stolen backup is a file of noise.
        $path = 'identity-documents/'.$request->user()->id.'/'.Str::uuid().'.enc';

        Storage::disk(IdentityDocument::DISK)->put(
            $path,
            Crypt::encryptString($file->get()),
        );

        $request->user()->identityDocuments()->create([
            'kind' => $validated['kind'],
            // The name the customer's own file had, kept only so they can tell
            // one upload from another on this page.
            'original_name' => Str::limit($file->getClientOriginalName(), 120, ''),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'status' => 'pending',
        ]);

        return back()->with('status', __('store.documents_uploaded'));
    }

    public function destroy(Request $request, IdentityDocument $document): RedirectResponse
    {
        abort_unless($document->user_id === $request->user()->id, 403);

        $document->forgetFile();
        $document->delete();

        return back()->with('status', __('store.documents_deleted'));
    }
}
