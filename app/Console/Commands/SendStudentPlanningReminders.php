<?php

namespace App\Console\Commands;

use App\Domains\Students\Services\BirthdayService;
use App\Domains\Students\Services\WaitlistAvailabilityService;
use Illuminate\Console\Command;

class SendStudentPlanningReminders extends Command
{
    protected $signature = 'students:planning-reminders';

    protected $description = 'Notify staff about birthdays and students about matching waitlist openings';

    public function handle(BirthdayService $birthdays, WaitlistAvailabilityService $waitlist): int
    {
        $this->info('Birthday reminders: '.$birthdays->remind().'; waitlist openings: '.$waitlist->notifyOpenings());

        return self::SUCCESS;
    }
}
