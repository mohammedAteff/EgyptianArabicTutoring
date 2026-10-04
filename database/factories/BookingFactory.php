<?php

namespace Database\Factories;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now('UTC')->addDays(10)->startOfHour();

        return array_merge(app(TimezoneService::class)->createBookingSnapshot($start, $start->copy()->addHour(), 'Africa/Cairo', 'Africa/Cairo'), [
            'contact_id' => fn () => Contact::query()->create(['name' => 'Synthetic booking contact', 'email' => fake()->unique()->safeEmail()])->id,
            'session_type_id' => fn () => SessionType::query()->create(['title' => 'Synthetic direct lesson', 'slug' => fake()->unique()->slug(), 'duration_minutes' => 60, 'price' => '40.00', 'currency' => 'USD', 'active' => true, 'funding_mode' => 'direct'])->id,
            'status' => 'confirmed', 'confirmation_token' => fake()->sha256(), 'idempotency_key' => fake()->uuid(), 'funding_mode' => 'direct',
        ]);
    }
}
