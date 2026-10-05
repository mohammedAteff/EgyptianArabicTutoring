<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResourceAssignment> */
class ResourceAssignmentFactory extends Factory
{
    protected $model = ResourceAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'resource_id' => ResourceFactory::new(), 'student_visible' => false];
    }
}
