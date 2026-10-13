<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\StudentPortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request, StudentPortalService $portal): Response
    {
        $portalData = $portal->data($request);
        $notifications = StudentNotification::query()->where('student_id', $request->attributes->get('student')->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        $destinations = $notifications->getCollection()->mapWithKeys(fn (StudentNotification $item): array => [
            $item->id => $item->type === 'lesson' && parse_url($item->link, PHP_URL_FRAGMENT) === 'history-title'
                ? route('student.dashboard')
                : $item->link,
        ]);

        return response()->view('student.notifications', ['notifications' => $notifications, 'destinations' => $destinations, 'timezone' => $portalData['timezone']])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $notification): RedirectResponse
    {
        $item = StudentNotification::query()->where('student_id', $request->attributes->get('student')->id)->findOrFail($notification);
        $item->update(['read_at' => $item->read_at ?? now('UTC')]);

        return to_route('student.notifications.index')->with('success', 'Notification marked as read.');
    }
}
