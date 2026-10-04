<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonMaterialRequest;
use App\Http\Requests\UpdateLessonMaterialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LessonWorkspaceController extends Controller
{
    public function show(Booking $booking, TimezoneService $timezones): Response
    {
        Gate::authorize('manageLessonWorkspace', $booking);
        $booking->load(['student', 'contact', 'sessionType', 'lessonMaterials.resource']);
        $sources = SessionLedgerEntry::query()->where('booking_id', $booking->id)->with('package:id,package_name')->orderBy('id')->get();

        return response()->view('admin.bookings.lesson-workspace', [
            'booking' => $booking, 'businessTimezone' => $timezones->getBusinessTimezone(),
            'resources' => Resource::query()->published()->orderBy('title')->get(['id', 'title']),
            'creditSources' => $sources,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreLessonMaterialRequest $request, Booking $booking, LessonMaterialService $materials): RedirectResponse
    {
        /** @var Administrator $actor */
        $actor = $request->user();
        $materials->attach($booking, $request->validated(), $actor->id);

        return to_route('admin.lessons.show', $booking)->with('success', 'Lesson material attached.');
    }

    public function update(UpdateLessonMaterialRequest $request, Booking $booking, LessonMaterial $material, LessonMaterialService $materials): RedirectResponse
    {
        abort_unless((int) $material->booking_id === (int) $booking->id, 404);
        /** @var Administrator $actor */
        $actor = $request->user();
        $materials->update($booking, $material, $request->validated(), $actor->id);

        return to_route('admin.lessons.show', $booking)->with('success', 'Lesson material updated.');
    }

    public function destroy(Request $request, Booking $booking, LessonMaterial $material, LessonMaterialService $materials): RedirectResponse
    {
        Gate::authorize('manageLessonWorkspace', $booking);
        abort_unless((int) $material->booking_id === (int) $booking->id, 404);
        /** @var Administrator $actor */
        $actor = $request->user();
        $cleaned = $materials->withdraw($booking, $material, $actor->id);

        return to_route('admin.lessons.show', $booking)->with($cleaned ? 'success' : 'warning',
            $cleaned ? 'Material withdrawn. Private file removed where applicable.' : 'Access withdrawn. Private file cleanup is pending; retry cleanup below.');
    }

    public function open(Booking $booking, LessonMaterial $material, LessonMaterialService $materials): Response
    {
        Gate::authorize('manageLessonWorkspace', $booking);
        abort_unless((int) $material->booking_id === (int) $booking->id, 404);
        $material->load(['booking', 'resource']);
        Gate::authorize('view', $material);

        return $materials->open($material);
    }
}
