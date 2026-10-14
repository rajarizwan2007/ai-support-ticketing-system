<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications from the bell. Each query goes through the signed-in
 * user's notifications, so nobody can read or change someone else's.
 */
class NotificationController extends Controller
{
    /**
     * Mark one notification as read and open its ticket.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return to_route('tickets.show', (string) $notification->data['ticket_reference']);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
