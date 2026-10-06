<?php

namespace Database\Factories;

use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BookingWaitlist> */
class BookingWaitlistFactory extends Factory
{
    protected $model = BookingWaitlist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'session_type_id' => SessionType::factory(), 'date_from' => now()->addWeek()->toDateString(), 'date_to' => now()->addWeeks(2)->toDateString(), 'timezone' => 'UTC', 'status' => 'open', 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', fake()->uuid())];
    }
}
