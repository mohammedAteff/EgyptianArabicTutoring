<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\AdminNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $query = AdminNotification::query()->recent();

        // Ordinary admin only sees operational notifications (bookings, resources, etc.), super_admin sees all
        if ($request->user()?->role !== 'super_admin') {
            $query->whereNotIn('type', ['backup_failure', 'system_warning']);
        }

        if ($filter === 'unread') {
            $query->unread();
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCountQuery = AdminNotification::query()->unread();
        if ($request->user()?->role !== 'super_admin') {
            $unreadCountQuery->whereNotIn('type', ['backup_failure', 'system_warning']);
        }
        $unreadCount = $unreadCountQuery->count();

        return view('admin.notifications.index', [
            'title' => 'Admin Notifications',
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markAsRead(AdminNotification $notification): RedirectResponse
    {
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $query = AdminNotification::query()->unread();

        if ($request->user()?->role !== 'super_admin') {
            $query->whereNotIn('type', ['backup_failure', 'system_warning']);
        }

        $query->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(AdminNotification $notification): RedirectResponse
    {
        $notification->delete();

        return back()->with('success', 'Notification dismissed.');
    }
}
