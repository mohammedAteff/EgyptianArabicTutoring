<?php

namespace App\Domains\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

class TutorWorkWorkspace
{
    public const KEY = 'sole-tutor';

    public const PROTOCOL = 'shared-work-v2';

    public function find(bool $lock = false): ?stdClass
    {
        if (! Schema::hasTable('tutor_work_workspaces')) {
            return null;
        }
        $query = DB::table('tutor_work_workspaces')->where('key', self::KEY);

        /** @var stdClass|null $workspace */
        $workspace = ($lock ? $query->lockForUpdate() : $query)->first();

        return $workspace;
    }

    public function ready(bool $lock = false): stdClass
    {
        $workspace = $this->find($lock);
        abort_unless($workspace !== null, 503, 'Shared Tutor Work awaits its approved database migration. Existing teaching reports remain available.');
        abort_unless($workspace->transition_state === 'ready', 423, 'Shared Tutor Work awaits an explicit legacy adoption or preservation decision. New work is blocked until cutover.');

        return $workspace;
    }
}
