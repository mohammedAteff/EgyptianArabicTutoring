<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use Illuminate\Support\Facades\DB;

class TutorWorkEntryPolicy
{
    public function viewAny(Administrator $user): bool
    {
        return $user->exists && Administrator::whereKey($user->id)->where('role', 'super_admin')->whereNull('suspended_at')->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first(['id']) !== null;
    }

    public function view(Administrator $user, TutorWorkEntry $entry): bool
    {
        return $this->viewAny($user) && $entry->workspace_id !== null && DB::table('tutor_work_workspaces')->where('key', TutorWorkWorkspace::KEY)->where('transition_state', 'ready')->where('id', $entry->workspace_id)->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first(['id']) !== null;
    }

    public function update(Administrator $user, TutorWorkEntry $entry): bool
    {
        return $this->view($user, $entry);
    }
}
