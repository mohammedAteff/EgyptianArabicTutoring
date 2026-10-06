<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Services\BookingPolicyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingPolicyController extends Controller
{
    public function index(BookingPolicyService $policies): View
    {
        return view('admin.scheduling.policy', ['policy' => $policies->current()]);
    }

    public function store(Request $request, BookingPolicyService $policies): RedirectResponse
    {
        $policies->save($request->only(['cancellation_cutoff_hours', 'late_cancellation', 'staff_cancellation', 'no_show']), (int) $request->user('web')->id);

        return back()->with('success', 'Policy saved for new bookings. Existing booking snapshots are preserved.');
    }
}
