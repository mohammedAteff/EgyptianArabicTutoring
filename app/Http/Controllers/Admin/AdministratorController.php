<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministratorController extends Controller
{
    public function index(): View
    {
        $administrators = Administrator::query()
            ->orderBy('name')
            ->paginate(20);

        return view('admin.administrators.index', [
            'title' => 'Administrator Management',
            'administrators' => $administrators,
        ]);
    }

    public function create(): View
    {
        return view('admin.administrators.create', [
            'title' => 'Create Administrator',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:administrators,email'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'assistant'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = Administrator::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'administrator_created',
            'entity_type' => Administrator::class,
            'entity_id' => $admin->id,
            'new_data' => [
                'email' => $admin->email,
                'name' => $admin->name,
                'role' => $admin->role,
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('admin.administrators.index')
            ->with('success', "Administrator {$admin->name} created successfully.");
    }

    public function edit(Administrator $administrator): View
    {
        return view('admin.administrators.edit', [
            'title' => "Edit Administrator — {$administrator->name}",
            'admin' => $administrator,
        ]);
    }

    public function update(Request $request, Administrator $administrator): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('administrators')->ignore($administrator->id)],
            'role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'assistant'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Prevent removing super_admin if it's the last super_admin
        if ($administrator->isSuperAdmin() && $validated['role'] !== 'super_admin') {
            $superAdminCount = Administrator::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot demote the last remaining Super Administrator.');
            }
        }

        $prev = [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => $administrator->role,
        ];

        $updateData = [
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $administrator->update($updateData);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'administrator_updated',
            'entity_type' => Administrator::class,
            'entity_id' => $administrator->id,
            'previous_data' => $prev,
            'new_data' => [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'role' => $administrator->role,
                'password_changed' => ! empty($validated['password']),
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('admin.administrators.index')
            ->with('success', "Administrator {$administrator->name} updated successfully.");
    }

    public function destroy(Administrator $administrator): RedirectResponse
    {
        // Prevent self-deletion
        if (Auth::id() === $administrator->id) {
            return back()->with('error', 'You cannot delete your own administrator account.');
        }

        // Prevent deleting the last super_admin
        if ($administrator->isSuperAdmin()) {
            $superAdminCount = Administrator::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot delete the last remaining Super Administrator.');
            }
        }

        $adminData = [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => $administrator->role,
        ];

        $administrator->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'administrator_deleted',
            'entity_type' => Administrator::class,
            'entity_id' => $administrator->id,
            'previous_data' => $adminData,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.administrators.index')
            ->with('success', "Administrator {$administrator->name} removed.");
    }
}
