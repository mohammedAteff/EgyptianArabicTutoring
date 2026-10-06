<?php

namespace Database\Factories;

use App\Domains\Booking\Models\RecurringLessonOccurrence;
use App\Domains\Booking\Models\RecurringLessonPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecurringLessonOccurrence> */
class RecurringLessonOccurrenceFactory extends Factory
{
    protected $model = RecurringLessonOccurrence::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['recurring_lesson_plan_id' => RecurringLessonPlan::factory(), 'sequence' => 1, 'local_date' => now()->addWeek()->toDateString(), 'status' => 'pending'];
    }
}
