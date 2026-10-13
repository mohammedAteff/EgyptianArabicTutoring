<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\StaffSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function search(Request $request, StaffSearchService $search): View|JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim($data['q'] ?? '');
        $items = $search->search($request->user('web'), $query);
        if ($request->expectsJson()) {
            return response()->json(['items' => $items])->header('Cache-Control', 'private, no-store');
        }

        return view('admin.search.index', ['title' => 'Global Search', 'query' => $query, 'items' => $items]);
    }
}
