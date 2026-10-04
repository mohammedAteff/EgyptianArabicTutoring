<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\EntitlementType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SessionTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.bookings.session-types', ['sessionTypes' => SessionType::query()->with('requiredEntitlementType')->orderBy('id')->get(), 'entitlementTypes' => EntitlementType::query()->where('active', true)->get()]);
    }

    public function save(Request $request, ?SessionType $sessionType = null): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('session_types')->ignore($sessionType?->id)], 'duration_minutes' => ['required', 'integer', 'between:15,240'], 'price' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'], 'currency' => ['required', 'size:3', 'alpha'], 'funding_mode' => ['required', Rule::in(['package', 'direct', 'free', 'legacy'])], 'required_entitlement_type_id' => ['required_if:funding_mode,package', 'nullable', Rule::exists('entitlement_types', 'id')->where('active', true)], 'required_entitlement_units' => ['required_if:funding_mode,package', 'nullable', 'integer', 'between:1,100'], 'active' => ['boolean']]);
        $data['active'] = $request->boolean('active');
        if ($data['funding_mode'] !== 'package') {
            $data['required_entitlement_type_id'] = null;
            $data['required_entitlement_units'] = null;
        }
        ($sessionType ?? new SessionType)->fill($data)->save();

        return back()->with('success', 'Lesson configuration saved. Existing booking entitlement snapshots are unchanged.');
    }
}
