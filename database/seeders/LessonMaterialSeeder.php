<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use Illuminate\Database\Seeder;

class LessonMaterialSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }
        $booking = Booking::query()->whereHas('contact', fn ($query) => $query->where('email', 'feature-qa-student@example.test'))->first();
        if ($booking && ! $booking->lessonMaterials()->exists()) {
            LessonMaterial::factory()->for($booking)->create(['title' => 'QA lesson preparation link']);
        }
    }
}
