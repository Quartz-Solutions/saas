<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notifications\MarkNotificationReadRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NotificationsController extends Controller
{
    /**
     * Mark a single notification as read. Notification id is the UUID from
     * the `notifications` table.
     */
    public function markRead(MarkNotificationReadRequest $request, string $id): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Notification marked as read.'),
        ]);

        return back();
    }

    /**
     * Mark every unread notification as read.
     */
    public function markAllRead(MarkAllNotificationsReadRequest $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('All notifications marked as read.'),
        ]);

        return back();
    }
}
