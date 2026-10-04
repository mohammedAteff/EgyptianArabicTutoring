<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Students\Models\Student;

class LessonMaterialPolicy
{
    public function __construct(private LessonWorkspacePolicy $workspaces) {}

    public function view(Administrator|Student $user, LessonMaterial $material): bool
    {
        if ($material->withdrawn_at || ! $material->booking || ! $this->workspaces->viewLessonWorkspace($user, $material->booking)) {
            return false;
        }

        return $user instanceof Administrator || ($material->student_visible
            && ($material->kind !== 'resource' || ($material->resource && $material->resource->isPublished())));
    }
}
