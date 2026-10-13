<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Resources\Models\ResourceCollection;
use App\Domains\Students\Models\ResourceCollectionAssignment;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceCollectionAssignment>
 */
class ResourceCollectionAssignmentFactory extends Factory
{
    protected $model = ResourceCollectionAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(), 'resource_collection_id' => ResourceCollection::factory(), 'student_visible' => true,
        ];
    }
}
