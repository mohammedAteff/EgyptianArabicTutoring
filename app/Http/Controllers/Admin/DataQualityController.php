<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Students\Services\DataQualityReadModel;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DataQualityController extends Controller
{
    public function index(DataQualityReadModel $reader): View
    {
        return view('admin.students.data-quality', $reader->overview());
    }
}
