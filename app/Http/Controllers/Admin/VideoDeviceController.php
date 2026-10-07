<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class VideoDeviceController extends Controller
{
    public function index(Request $request, Student $student): Response
    {
        $this->authorizeStudent($request, $student);

        return response()->view('admin.lms.video.devices', ['title' => 'Authorized video browsers', 'student' => $student,
            'devices' => AuthorizedDevice::query()->where('student_id', $student->id)->orderByDesc('id')->get(),
            'leases' => PlaybackLease::query()->where('student_id', $student->id)->where(fn ($query) => $query->where('authorized_until', '>', now('UTC'))->orWhere(fn ($query) => $query->where('status', 'active')->where('expires_at', '>', now('UTC'))))->get()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function revoke(Request $request, Student $student, int $device, VideoDeviceService $devices): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $devices->revoke($student, $device);

        return redirect()->route('admin.lms.video.devices', $student)->with('success', 'Browser revoked. Already issued links expire shortly.');
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize('manageTeaching', $student);
    }
}
