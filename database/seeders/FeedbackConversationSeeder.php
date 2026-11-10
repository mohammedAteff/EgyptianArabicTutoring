<?php

namespace Database\Seeders;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeedbackConversationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing', 'feedback-browser')) {
            throw new \RuntimeException('Feedback demo fixtures are restricted to disposable test environments.');
        }
        $student = Student::factory()->verified()->create(['email' => 'feedback-demo-'.Str::uuid().'@example.test']);
        app(FeedbackService::class)->create($student, [
            'request_key' => (string) Str::uuid(), 'subject' => 'Synthetic feedback example',
            'body' => 'Could you help me find the next lesson?', 'type' => 'question', 'area' => 'learning',
        ]);
    }
}
