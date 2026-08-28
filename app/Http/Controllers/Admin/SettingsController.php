<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\AdminActivityLog;
use App\Models\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index');
    }

    public function orders(): View
    {
        return view('admin.settings.orders', [
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * The email test bench: a send form and the current transport laid bare
     * (no secrets), so "why did nothing arrive" can be answered from one page.
     */
    public function email(): View
    {
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp', []);

        return view('admin.settings.email', [
            'mailer' => $mailer,
            'diagnostics' => array_filter([
                'Transport' => $mailer,
                'Host' => $mailer === 'smtp' ? ($smtp['host'] ?? null) : null,
                'Port' => $mailer === 'smtp' ? ($smtp['port'] ?? null) : null,
                // No forced scheme doesn't mean cleartext: the transport
                // upgrades to STARTTLS on its own whenever the server offers
                // it, and "none" would wrongly read as an alarm.
                'Encryption' => $mailer === 'smtp' ? ($smtp['scheme'] ?? 'auto (STARTTLS when offered)') : null,
                'Username' => $mailer === 'smtp' ? (filled($smtp['username'] ?? null) ? 'set' : 'not set') : null,
                'Password' => $mailer === 'smtp' ? (filled($smtp['password'] ?? null) ? 'set' : 'not set') : null,
                'From address' => (string) config('mail.from.address'),
                'From name' => (string) config('mail.from.name'),
            ]),
        ]);
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        // A dead SMTP server answers here as a message on the page, not as a
        // stack trace: failing is exactly what this page exists to show.
        try {
            Mail::to($validated['email'])->send(new TestMail());
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Sending failed: '.$e->getMessage()]);
        }

        AdminActivityLog::record('settings.test_email_sent', null, 'Sent a test email to '.$validated['email']);

        return back()->with('status', 'Test email sent to '.$validated['email'].'.'.((string) config('mail.default') === 'log' ? ' Note: the "log" transport only writes it to the log file.' : ''));
    }
}
