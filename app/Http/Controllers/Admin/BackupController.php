<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backups of everything the code cannot recreate.
 *
 * Owner only, like the accounts: an archive holds every order and every
 * customer's address, and it is served from outside public/ for the same
 * reason.
 */
class BackupController extends Controller
{
    /** The archives already taken, newest first. */
    public function index(): View
    {
        return view('admin.backups.index', [
            'backups' => SiteBackup::all(),
            'totalSize' => SiteBackup::totalSize(),
        ]);
    }

    /**
     * Writes a new archive, in the request.
     *
     * It takes as long as it takes — a minute or so on a full catalogue. The
     * page is visited rarely and nothing else here runs in the background, so
     * waiting is plainer than pretending it finished.
     */
    public function store(): RedirectResponse
    {
        try {
            $name = SiteBackup::create();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.backups.index')
                ->withErrors(['backup' => 'The backup could not be written: '.$exception->getMessage()]);
        }

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Backup created: '.$name);
    }

    /** Hands over one archive. */
    public function show(string $name): BinaryFileResponse
    {
        $path = SiteBackup::path($name);

        abort_if($path === null, 404);

        return response()->download($path, $name, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(string $name): RedirectResponse
    {
        abort_unless(SiteBackup::delete($name), 404);

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Backup deleted.');
    }
}
