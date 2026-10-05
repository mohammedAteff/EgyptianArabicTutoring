<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonFeedback> */
class LessonFeedbackFactory extends Factory
{
    protected $model = LessonFeedback::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['booking_id' => Booking::factory()->for(Student::factory()->verified(), 'student')->state(['status' => 'completed']), 'student_id' => fn (array $attributes) => Booking::findOrFail($attributes['booking_id'])->student_id, 'rating' => 4, 'comment' => 'Helpful lesson'];
    }
}
