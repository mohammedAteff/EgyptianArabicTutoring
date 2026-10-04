<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LessonWorkspaceController extends Controller
{
    private function ownedBooking(Request $request, int $booking): Booking
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $owned = Booking::query()->where('student_id', $student->id)->findOrFail($booking);
        abort_unless(Gate::forUser($student)->allows('viewLessonWorkspace', $owned), 404);

        return $owned;
    }

    public function show(Request $request, int $booking, TimezoneService $timezones): Response
    {
        $owned = $this->ownedBooking($request, $booking);
        $owned->load(['sessionType', 'lessonMaterials' => fn ($query) => $query->visibleToStudent()->select(['id', 'booking_id', 'kind', 'title', 'description', 'sort_order'])]);

        return response()->view('student.lesson-workspace', [
            'booking' => $owned, 'businessTimezone' => $timezones->getBusinessTimezone(),
            'timezone' => $request->session()->get('student_display_timezone', $owned->customer_timezone),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request, int $booking, int $material, LessonMaterialService $materials): Response
    {
        $owned = $this->ownedBooking($request, $booking);
        $item = $owned->lessonMaterials()->with(['booking', 'resource'])->findOrFail($material);
        /** @var Student $student */
        $student = $request->attributes->get('student');
        abort_unless(Gate::forUser($student)->allows('view', $item), 404);

        return $materials->open($item);
    }
}
