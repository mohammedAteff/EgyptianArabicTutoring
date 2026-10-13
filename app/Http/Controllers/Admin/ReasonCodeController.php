<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Financial\Models\ReasonCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReasonCodeController extends Controller
{
    public const CATEGORIES = ['refund', 'courtesy_credit', 'validity_extension', 'suspension', 'cancellation', 'reschedule', 'operational_status'];

    public function index(): View
    {
        return view('admin.reason-codes', ['reasons' => ReasonCode::query()->orderBy('category')->orderBy('sort_order')->get(), 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['category' => ['required', Rule::in(self::CATEGORIES)], 'code' => ['nullable', 'alpha_dash', 'max:40', Rule::unique('reason_codes')->where('category', $request->input('category'))], 'label' => ['required', 'string', 'max:190']]);
        $data['code'] ??= substr(Str::slug($data['label'], '_'), 0, 28).'_'.Str::lower(Str::random(8));
        $reason = ReasonCode::query()->create($data + ['is_active' => true]);
        $audit->log('reason_code_created', ReasonCode::class, $reason->id, null, $data);

        return back()->with('success', 'Reason category added.');
    }

    public function update(Request $request, ReasonCode $reason, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:190'], 'is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,1000']]);
        $reason->update($data);
        $audit->log('reason_code_updated', ReasonCode::class, $reason->id, null, $data);

        return back()->with('success', 'Reason category updated.');
    }
}
