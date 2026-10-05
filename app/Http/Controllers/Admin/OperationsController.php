<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\OperationsReadModel;
use App\Domains\Administration\Services\StaffRecentViewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(Request $request, OperationsReadModel $operations, StaffRecentViewService $recent): View
    {
        return view('admin.operations', [...$operations->forAdministrator($request->user('web')), 'title' => 'Today & Operations', 'recentViews' => $recent->forAdministrator($request->user('web'))]);
    }
}
