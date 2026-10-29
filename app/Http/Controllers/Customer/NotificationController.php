<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->markAsRead();

        return back()->with('status', 'Alert marked as read.');
    }

    /**
     * Readme: opening an alert's details automatically marks it as read, so
     * the list page never needs a separate "mark as read" chore. Links are
     * generated internally, but the host check keeps a tampered absolute
     * host in the column from turning this route into an open redirect.
     */
    public function open(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->markAsRead();

        $link = (string) $notification->link;

        // Same-site paths only; "//host" is protocol-relative and absolute
        // URLs must still land on this app's host.
        $sameSite = str_starts_with($link, '/')
            ? ! str_starts_with($link, '//')
            : $this->isLocalAbsoluteUrl($link);

        if ($link !== '' && ! $sameSite) {
            $link = '';
        }

        return redirect($link !== '' ? $link : route('notifications.index'));
    }

    private function isLocalAbsoluteUrl(string $link): bool
    {
        $host = parse_url($link, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        // Links are generated with route(), so they may carry either the
        // configured APP_URL host or the one the app was served on; both are
        // ours, anything else was planted.
        $locals = [
            (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            request()->getHost(),
        ];

        foreach ($locals as $local) {
            if ($local !== '' && strcasecmp($host, $local) === 0) {
                return true;
            }
        }

        return false;
    }
}
