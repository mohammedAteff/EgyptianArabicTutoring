<?php

namespace Database\Factories;

use App\Domains\Administration\Models\StaffTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffTask> */
class StaffTaskFactory extends Factory
{
    protected $model = StaffTask::class;

    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'assignee_id' => AdministratorFactory::new(), 'created_by' => AdministratorFactory::new(), 'status' => 'open', 'priority' => 'normal'];
    }
}
