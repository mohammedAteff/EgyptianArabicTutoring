<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.payment-methods', ['title' => 'Payment Methods', 'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:payment_methods,name'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000']]);
        $method = PaymentMethod::create([...$data, 'active' => true]);
        $audit->log('payment_method_created', PaymentMethod::class, $method->id, null, $data, $request->user('web')->id);

        return back()->with('success', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('payment_methods', 'name')->ignore($paymentMethod->id)], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'], 'active' => ['required', 'boolean'], 'is_default' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $paymentMethod, $data, $audit): void {
            $methods = PaymentMethod::query()->orderBy('id')->lockForUpdate()->get();
            $method = $methods->firstWhere('id', $paymentMethod->id);
            if ($data['is_default'] && ! $data['active']) {
                throw ValidationException::withMessages(['active' => 'The default payment method must be enabled. Choose another default first.']);
            }
            if (! $data['active'] && $method->is_default && ! $methods->where('id', '!=', $method->id)->where('active', true)->isNotEmpty()) {
                throw ValidationException::withMessages(['active' => 'Keep at least one enabled payment method.']);
            }
            $old = $method->only(['name', 'active', 'sort_order', 'is_default']);
            if ($data['is_default']) {
                PaymentMethod::query()->update(['is_default' => false]);
            }
            $method->update($data);
            if (! PaymentMethod::query()->where('active', true)->where('is_default', true)->exists()) {
                PaymentMethod::available()->firstOrFail()->update(['is_default' => true]);
            }
            $audit->log('payment_method_updated', PaymentMethod::class, $method->id, $old, $data, $request->user('web')->id);
        }, 5);

        return back()->with('success', 'Payment method saved. Recorded payment labels are preserved.');
    }
}
