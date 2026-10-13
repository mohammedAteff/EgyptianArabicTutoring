<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\StaffNoteTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffNoteTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = StaffNoteTemplate::query()
            ->when($request->query('category'), fn ($q, $cat) => $q->where('category', $cat))
            ->orderBy('category')
            ->orderBy('title')
            ->get(['id', 'title', 'category', 'body']);

        return response()->json($templates);
    }

    public function library(): View
    {
        return view('admin.staff-note-templates', ['templates' => StaffNoteTemplate::query()->orderBy('category')->orderBy('title')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        StaffNoteTemplate::create([
            ...$data,
            'created_by' => $request->user('web')->id,
        ]);

        return back()->with('success', 'Note template saved.');
    }
}
