<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Model;

class LmsPolicy
{
    public function __construct(private LmsAccessService $access) {}

    public function manage(Administrator|Student $actor): bool
    {
        return $actor instanceof Administrator
            && Administrator::query()->whereKey($actor->id)->whereNull('suspended_at')->whereIn('role', ['admin', 'super_admin'])->exists();
    }

    public function view(Administrator|Student|null $actor, Model $target): bool
    {
        return ! ($actor instanceof Administrator) && $this->access->canAccess($actor, $target);
    }
}
