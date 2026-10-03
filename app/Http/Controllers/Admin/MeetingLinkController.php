<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeetingLinkController extends Controller
{
    public function index(): View
    {
        return view('admin.bookings.meeting-links', ['title' => 'Meeting Links', 'providers' => MeetingProvider::query()->orderBy('sort_order')->get(), 'rooms' => MeetingRoom::query()->with('provider')->orderBy('id')->get(), 'revealMinutes' => Setting::get('meeting.student_reveal_minutes', 15), 'bookings' => Booking::query()->with(['student', 'contact', 'sessionType'])->where('status', 'confirmed')->where('end_at_utc', '>', now('UTC'))->orderBy('start_at_utc')->limit(100)->get()]);
    }

    public function provider(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:meeting_providers,id'], 'name' => ['required', 'string', 'max:80', Rule::unique('meeting_providers', 'name')->ignore($request->integer('id'))], 'icon' => ['nullable', 'string', 'max:20'], 'active' => ['required', 'boolean'], 'is_default' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000']]);
        DB::transaction(function () use ($request, $data, $audit): void {
            MeetingProvider::query()->orderBy('id')->lockForUpdate()->get();
            if ($data['is_default'] && ! $data['active']) {
                throw ValidationException::withMessages(['active' => 'The default provider must be enabled.']);
            }
            $provider = ! empty($data['id']) ? MeetingProvider::query()->findOrFail($data['id']) : new MeetingProvider;
            $old = $provider->exists ? $provider->only(['name', 'active', 'is_default', 'sort_order']) : null;
            if ($data['is_default']) {
                MeetingProvider::query()->update(['is_default' => false]);
            }
            $provider->fill(collect($data)->except('id')->all())->save();
            if (! MeetingProvider::query()->where('active', true)->where('is_default', true)->exists()) {
                $default = MeetingProvider::query()->where('active', true)->orderBy('sort_order')->first();
                if (! $default) {
                    throw ValidationException::withMessages(['active' => 'Keep at least one enabled provider.']);
                }
                $default->update(['is_default' => true]);
            }
            $audit->log('meeting_provider_saved', MeetingProvider::class, $provider->id, $old, $provider->only(['name', 'active', 'is_default', 'sort_order']), $request->user('web')->id);
        }, 5);

        return back()->with('success', 'Provider saved. Existing assignments retain their snapshots.');
    }

    public function room(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['id' => ['nullable', 'integer', 'exists:meeting_rooms,id'], 'meeting_provider_id' => ['required', 'integer', 'exists:meeting_providers,id'], 'name' => ['required', 'string', 'max:160'], 'url' => ['required', 'url:https', 'max:2000'], 'active' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:4000']]);
        $hash = hash('sha256', trim($data['url']));
        if (MeetingRoom::query()->where('url_hash', $hash)->when(! empty($data['id']), fn ($q) => $q->where('id', '!=', $data['id']))->exists()) {
            throw ValidationException::withMessages(['url' => 'This URL is already in the room pool. Edit its existing entry.']);
        }
        try {
            DB::transaction(function () use ($request, $data, $hash, $audit): void {
                $room = ! empty($data['id']) ? MeetingRoom::query()->lockForUpdate()->findOrFail($data['id']) : new MeetingRoom;
                $old = $room->exists ? $room->only(['name', 'active', 'meeting_provider_id']) : null;
                $room->fill([...collect($data)->except('id')->all(), 'url' => trim($data['url']), 'url_hash' => $hash])->save();
                $audit->log('meeting_room_saved', MeetingRoom::class, $room->id, $old, $room->only(['name', 'active', 'meeting_provider_id']), $request->user('web')->id);
            }, 5);

        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['url' => 'This URL is already in the room pool. Edit its existing entry.']);
        }

        return back()->with('success', 'Room saved. Assigned lesson URLs are preserved.');
    }

    public function settings(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['reveal_minutes' => ['required', 'integer', 'between:0,1440']]);
        $old = Setting::get('meeting.student_reveal_minutes', 15);
        Setting::set('meeting.student_reveal_minutes', $data['reveal_minutes'], 'booking');
        $audit->log('meeting_reveal_time_updated', Setting::class, null, ['reveal_minutes' => $old], $data, $request->user('web')->id);

        return back()->with('success', 'Student link reveal time saved.');
    }

    public function assign(Request $request, Booking $booking, MeetingLinkService $links): RedirectResponse
    {
        $data = $request->validate(['meeting_room_id' => ['required', 'integer', 'exists:meeting_rooms,id']]);
        $links->assign($booking, roomId: $data['meeting_room_id'], actorId: $request->user('web')->id);

        return back()->with('success', 'Lesson room assigned.');
    }

    public function reconcile(MeetingLinkService $links): RedirectResponse
    {
        $result = $links->reconcileUpcoming();

        return back()->with('success', "Assigned {$result['assigned']} lessons; {$result['needed']} still need an eligible room.");
    }

    public function preference(Request $request, Student $student, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['preferred_meeting_provider_id' => ['nullable', 'integer', Rule::exists('meeting_providers', 'id')->where('active', true)]]);
        $old = $student->only('preferred_meeting_provider_id');
        $student->update($data);
        $audit->log('student_meeting_preference_updated', Student::class, $student->id, $old, $data, $request->user('web')->id);

        return back()->with('success', 'Meeting provider preference saved for future assignments.');
    }
}
