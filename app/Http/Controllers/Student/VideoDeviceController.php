<?php

namespace App\Http\Controllers\Student;

use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentPortalService;
use App\Domains\Students\Services\StudentSessionContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VideoDeviceController extends Controller
{
    public function __construct(private StudentSessionContext $sessions, private VideoDeviceService $devices) {}

    public function index(Request $request, StudentPortalService $portal): Response
    {
        $student = $this->student($request);

        return response()->view('student.learning.devices', ['devices' => AuthorizedDevice::query()->where('student_id', $student->id)->orderByDesc('id')->get()] + $portal->data($request), 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function register(Request $request): RedirectResponse
    {
        $this->devices->register($request, $this->student($request));

        return redirect()->route('student.video.devices')->with('status', 'This browser is authorized. Individual videos may have a lower browser limit.');
    }

    public function update(Request $request, int $device): RedirectResponse
    {
        $values = $request->validate(['label' => ['required', 'string', 'max:80']]);
        $this->devices->rename($this->student($request), $device, $values['label']);

        return redirect()->route('student.video.devices')->with('status', 'Browser label updated.');
    }

    public function revoke(Request $request, int $device): RedirectResponse
    {
        $this->devices->revoke($this->student($request), $device);

        return redirect()->route('student.video.devices')->with('status', 'Browser revoked. Playback renewal stops; an already issued link can remain valid briefly.');
    }

    private function student(Request $request): Student
    {
        $student = $this->sessions->current($request);
        abort_unless($student !== null, 404);

        return $student;
    }
}
