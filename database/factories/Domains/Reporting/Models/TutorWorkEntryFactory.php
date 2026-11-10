<?php

namespace Database\Factories\Domains\Reporting\Models;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TutorWorkEntry> */
class TutorWorkEntryFactory extends Factory
{
    protected $model = TutorWorkEntry::class;

    public function definition(): array
    {
        return ['workspace_id' => fn (): int => (int) app(TutorWorkWorkspace::class)->ready()->id, 'created_by' => Administrator::factory(), 'administrator_id' => null, 'origin' => 'manual', 'placement' => 'unplaced', 'lifecycle' => 'stopped', 'acceptance' => 'accepted', 'category' => 'lesson_preparation', 'timezone' => 'UTC', 'work_date' => now('UTC')->toDateString(), 'unplaced_seconds' => 3600, 'original_declaration' => ['fixture' => true]];
    }

    public function legacy(): static
    {
        return $this->state(fn (): array => ['workspace_id' => null, 'created_by' => null, 'administrator_id' => Administrator::factory()]);
    }
}
