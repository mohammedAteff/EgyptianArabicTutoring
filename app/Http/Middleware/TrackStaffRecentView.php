<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\StaffRecentViewService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackStaffRecentView
{
    public function __construct(private StaffRecentViewService $recent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $administrator = $request->user('web');
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && $administrator instanceof Administrator && ! $administrator->suspended_at && ! $administrator->trashed() && ($administrator->isAdmin() || $administrator->isAssistant())) {
            $type = match ($request->route()?->getName()) {
                'admin.students.show', 'admin.students.teaching' => 'student',
                'admin.bookings.show', 'admin.lessons.show' => 'booking',
                'admin.contacts.show' => 'contact',
                default => null,
            };
            if ($type !== null) {
                $entity = $request->route($type);
                $id = $entity instanceof Model ? $entity->getKey() : $entity;
                if (is_numeric($id)) {
                    $this->recent->record($administrator, $type, (int) $id);
                }
            }
        }

        return $response;
    }
}
