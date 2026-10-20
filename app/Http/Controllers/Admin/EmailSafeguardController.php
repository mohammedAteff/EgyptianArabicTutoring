<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailSafeguardController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate(['email_pin_preference' => ['required', Rule::in(['off', 'request_activation'])]]);
        Setting::set('email.pin_preference', $values['email_pin_preference'], 'security');
        app(AuditLogService::class)->log('email_verification_preference_updated', Setting::class, 0, null, $values, $request->user('web')->id);

        return back()->with('success', 'Email verification preference saved. PIN verification remains inactive until its delivery and verification flow is implemented.');
    }
}
