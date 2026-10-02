<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountSuspensionController extends Controller
{
    public function update(Request $request, string $type, int $account, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('web')?->isSuperAdmin(), 403);
        abort_unless(in_array($type, ['student', 'administrator'], true), 404);
        $data = $request->validate(['suspend' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $type, $account, $data, $audit): void {
            $administrators = Administrator::query()->orderBy('id')->lockForUpdate()->get();
            $actor = $administrators->firstWhere('id', $request->user('web')->id);
            if (! $actor || ! $actor->isSuperAdmin() || $actor->suspended_at !== null) {
                throw ValidationException::withMessages(['suspend' => 'Your account is no longer authorized to change account access.']);
            }
            $target = $type === 'administrator' ? $administrators->firstWhere('id', $account) : Student::query()->lockForUpdate()->findOrFail($account);
            abort_unless($target !== null, 404);
            if ($data['suspend'] && $type === 'administrator') {
                if ($target->id === $request->user('web')->id || ($target->isSuperAdmin() && $administrators->where('role', 'super_admin')->whereNull('suspended_at')->count() <= 1)) {
                    throw ValidationException::withMessages(['suspend' => 'You cannot suspend yourself or the last available Super Administrator.']);
                }
            }
            $old = ['suspended_at' => $target->suspended_at?->toIso8601String()];
            $target->update(['suspended_at' => $data['suspend'] ? now('UTC') : null, 'suspended_by' => $data['suspend'] ? $request->user('web')->id : null, 'suspension_reason' => $data['suspend'] ? $data['reason'] : null]);
            $audit->log($data['suspend'] ? 'account_suspended' : 'account_restored', get_class($target), $target->id, $old, ['reason' => $data['reason'], 'suspended' => (bool) $data['suspend']], $request->user('web')->id);
            DB::afterCommit(fn () => Cache::forget('student_auth_check_'.$account));
        }, 5);

        return back()->with('success', $data['suspend'] ? 'Account suspended.' : 'Account restored.');
    }
}
