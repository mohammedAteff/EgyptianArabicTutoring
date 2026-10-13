<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Services\MeetingRoomUsageReadModel;
use App\Domains\Reporting\Services\BusinessActivityReadModel;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessActivityController extends Controller
{
    public function index(Request $request, BusinessActivityReadModel $activity, MeetingRoomUsageReadModel $rooms, TimezoneService $timezones): View
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? now($timezones->getBusinessTimezone())->toDateString();
        $data = $activity->day($date, $request->user('web')->isSuperAdmin());
        $data['rooms'] = $rooms->overview(substr($date, 0, 7).'-01', $date);
        $data['staffNames'] = Administrator::withTrashed()->whereIn('id', $data['staffActivity']->pluck('administrator_id'))->get()->keyBy('id');

        return view('admin.reports.activity', $data);
    }
}
